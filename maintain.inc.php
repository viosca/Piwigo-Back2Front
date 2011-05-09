<?php
if (!defined('PHPWG_ROOT_PATH')) die('Hacking attempt!');

function plugin_install() {
	global $prefixeTable;

  /* create table for recto/veros pairs | stores original verso categories */
	pwg_query("CREATE TABLE IF NOT EXISTS `" . $prefixeTable . "image_verso` (
    `image_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
    `verso_id` mediumint(8) unsigned NOT NULL DEFAULT '0',
    `categories` varchar(128) NULL,
    PRIMARY KEY (`image_id`),
    UNIQUE KEY (`verso_id`)
	) DEFAULT CHARSET=utf8;");
  
  /* create a virtual category to store versos */
  $versos_cat = create_virtual_category('Back2Front private album');
  $versos_cat = array(
    'id' => $versos_cat['id'],
    'comment' => 'Used by Back2Front to store backsides.',
    'status'  => 'private',
    'visible' => 'false',
    'commentable' => 'false',
    );
  mass_updates(
    CATEGORIES_TABLE,
    array(
      'primary' => array('id'),
      'update' => array_diff(array_keys($versos_cat), array('id'))
      ),
    array($versos_cat)
    );
    
  /* config parameter */
  pwg_query("INSERT INTO `" . CONFIG_TABLE . "`
    VALUES ('back2front', '".$versos_cat['id']."', 'Configuration for Back2Front plugin');");
}

function plugin_uninstall() {
	global $conf, $prefixeTable;
  
  $conf['back2front'] = explode(',',$conf['back2front']);
  
  /* versos must be restored to their original categories
   criterias :
    - verso � 'versos' cat only => restore verso to original categories
    - otherwise nothing is changed
  */

  $query = "SELECT * FROM `" . $prefixeTable . "image_verso`;";
  $images_versos = pwg_query($query);
  
  while ($item = pwg_db_fetch_assoc($images_versos))
  {
    /* catch current verso categories */
    $versos_infos = pwg_query("SELECT category_id FROM ".IMAGE_CATEGORY_TABLE." WHERE image_id = ".$item['verso_id'].";");
    while (list($verso_cat) = pwg_db_fetch_row($versos_infos))
    {
      $item['current_verso_cats'][] = $verso_cat;
    }
    
    /* if verso � 'versos' cat only */
    if (count($item['current_verso_cats']) == 1 AND $item['current_verso_cats'][0] == $conf['back2front'][0])
    {
      foreach (explode(',',$item['categories']) as $cat)
      {
        $datas[] = array(
          'image_id' => $item['verso_id'],
          'category_id' => $cat,
          );
      }
    }
    
    pwg_query("DELETE FROM ".IMAGE_CATEGORY_TABLE."
      WHERE image_id = ".$item['verso_id']." AND category_id = ".$conf['back2front'][0].";");
  }
  
  if (isset($datas))
  {
    mass_inserts(
      IMAGE_CATEGORY_TABLE,
      array('image_id', 'category_id'),
      $datas
      );
  }

	pwg_query("DROP TABLE `" . $prefixeTable . "image_verso`;");
  pwg_query("DELETE FROM `" . CONFIG_TABLE . "` WHERE param = 'back2front';");
  pwg_query("DELETE FROM `" . CATEGORIES_TABLE ."`WHERE id = ".$conf['back2front'][0].";");
  
  /* rebuild categories cache */
  include_once(PHPWG_ROOT_PATH.'admin/include/functions.php');
  invalidate_user_cache(true);
}
?>