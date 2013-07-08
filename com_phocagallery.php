<?php

/**
 * @author     Branko Wilhelm <branko.wilhelm@gmail.com>
 * @link       http://www.z-index.net
 * @copyright  (c) 2013 Branko Wilhelm
 * @license    GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */
 
defined('_JEXEC') or die;

final class xmap_com_phocagallery {
	
	public function __construct() {
		require_once JPATH_ADMINISTRATOR . '/components/com_phocagallery/libraries/phocagallery/path/route.php';
	}
	
	public function getTree(&$xmap, &$parent, &$params) {
		$include_images = self::getParam($params, 'include_images', 1);
		$include_images = ($include_images == 1 || ($include_images == 2 && $xmap->view == 'xml') || ($include_images == 3 && $xmap->view == 'html'));
		$params['include_images'] = $include_images;
		
		$priority = self::getParam($params, 'category_priority', $parent->priority);
		$changefreq = self::getParam($params, 'category_changefreq', $parent->changefreq);
		
		if($priority == -1) {
			$priority = $parent->priority;
		}
		
		if($changefreq == -1) {
			$changefreq = $parent->changefreq;
		}
		
		$params['category_priority'] = $priority;
		$params['category_changefreq'] = $changefreq;
		
		$priority = self::getParam($params, 'image_priority', $parent->priority);
		$changefreq = self::getParam($params, 'image_changefreq', $parent->changefreq);
		
		if($priority == -1) {
			$priority = $parent->priority;
		}
		
		if($changefreq == -1) {
			$changefreq = $parent->changefreq;
		}
		
		$params['image_priority'] = $priority;
		$params['image_changefreq'] = $changefreq;
		
		self::getCategoryTree($xmap, $parent, $params, 0);
		return true;
	}
	
	private static function getCategoryTree(&$xmap, &$parent, &$params, $parent_id) {
		$db = JFactory::getDbo();
		
		$query = $db->getQuery(true)
				->select(array('id', 'title', 'parent_id'))
				->from('#__phocagallery_categories')
				->where('parent_id = ' . $db->quote($parent_id))
				->where('published = 1')
				->order('ordering');
		
		$db->setQuery($query);
		$rows = $db->loadObjectList();
		
		if(empty($rows)) {
			return;
		}
		
		$xmap->changeLevel(1);
		
		foreach($rows as $row) {
			$node = new stdclass;
			$node->id = $parent->id;
			$node->name = $row->title;
			$node->uid = $parent->uid . '_cid_' . $row->id;
			$node->browserNav = $parent->browserNav;
			$node->priority = $params['category_priority'];
			$node->changefreq = $params['category_changefreq'];
			$node->pid = $row->parent_id;
			$node->link = PhocaGalleryRoute::getCategoryRoute($row->id);
			
			if ($xmap->printNode($node) !== false) {
				self::getCategoryTree($xmap, $parent, $params, $row->id);
				if ($params['include_images']) {
					self::getDownloads($xmap, $parent, $params, $row->id);
				}
			}
		}
		
		$xmap->changeLevel(-1);
	}

	private static function getDownloads(&$xmap, &$parent, &$params, $catid) {
		$db = JFactory::getDbo();
		
		$query = $db->getQuery(true)
				->select(array('id', 'title', 'date'))
				->from('#__phocagallery')
				->where('catid = ' . $db->Quote($catid))
				->where('published = 1')
				->order('ordering');
		
		$db->setQuery($query);
		$rows = $db->loadObjectList();
		
		if(empty($rows)) {
			return;
		}
		
		$xmap->changeLevel(1);
		
		foreach($rows as $row) {
			$node = new stdclass;
			$node->id = $parent->id;
			$node->name = $row->title;
			$node->uid = $parent->uid . '_' . $row->id;
			$node->browserNav = $parent->browserNav;
			$node->priority = $params['image_priority'];
			$node->changefreq = $params['image_changefreq'];
			$node->link = PhocaGalleryRoute::getImageRoute($row->id, $catid);
			
			$xmap->printNode($node);
		}
		
		$xmap->changeLevel(-1);
	}
	
	private static function getParam($arr, $name, $default) {
		return JArrayHelper::getValue($arr, $name, $default);
	}
}