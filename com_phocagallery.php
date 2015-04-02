<?php

/**
 * @author     Branko Wilhelm <branko.wilhelm@gmail.com>
 * @link       http://www.z-index.net
 * @copyright  (c) 2013 - 2015 Branko Wilhelm
 * @license    GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

defined('_JEXEC') or die;

class xmap_com_phocagallery
{
    /**
     * @var array
     */
    private static $views = array('categories', 'category');

    /**
     * @var bool
     */
    private static $enabled = false;

    public function __construct()
    {
        self::$enabled = JComponentHelper::isEnabled('com_phocagallery');

        JLoader::register('PhocaGalleryRoute', JPATH_ADMINISTRATOR . '/components/com_phocagallery/libraries/phocagallery/path/route.php');
    }

    /**
     * @param XmapDisplayerInterface $xmap
     * @param stdClass $parent
     * @param array $params
     *
     * @throws Exception
     */
    public static function getTree($xmap, stdClass $parent, array &$params)
    {
        $uri = new JUri($parent->link);

        if (!self::$enabled || !in_array($uri->getVar('view'), self::$views)) {
            return;
        }

        $params['groups'] = implode(',', JFactory::getUser()->getAuthorisedViewLevels());

        $params['language_filter'] = JFactory::getApplication()->getLanguageFilter();

        $params['enable_imagemap'] = JArrayHelper::getValue($params, 'enable_imagemap', 0);

        $params['image_type'] = JArrayHelper::getValue($params, 'image_type', 'original');

        $params['include_images'] = JArrayHelper::getValue($params, 'include_images', 1);
        $params['include_images'] = ($params['include_images'] == 1 || ($params['include_images'] == 2 && $xmap->view == 'xml') || ($params['include_images'] == 3 && $xmap->view == 'html'));

        $params['show_unauth'] = JArrayHelper::getValue($params, 'show_unauth', 0);
        $params['show_unauth'] = ($params['show_unauth'] == 1 || ($params['show_unauth'] == 2 && $xmap->view == 'xml') || ($params['show_unauth'] == 3 && $xmap->view == 'html'));

        $params['category_priority'] = JArrayHelper::getValue($params, 'category_priority', $parent->priority);
        $params['category_changefreq'] = JArrayHelper::getValue($params, 'category_changefreq', $parent->changefreq);

        if ($params['category_priority'] == -1) {
            $params['category_priority'] = $parent->priority;
        }

        if ($params['category_changefreq'] == -1) {
            $params['category_changefreq'] = $parent->changefreq;
        }

        $params['image_priority'] = JArrayHelper::getValue($params, 'image_priority', $parent->priority);
        $params['image_changefreq'] = JArrayHelper::getValue($params, 'image_changefreq', $parent->changefreq);

        if ($params['image_priority'] == -1) {
            $params['image_priority'] = $parent->priority;
        }

        if ($params['image_changefreq'] == -1) {
            $params['image_changefreq'] = $parent->changefreq;
        }

        self::getCategoryTree($xmap, $parent, $params, $uri->getVar('id', 0));
    }

    /**
     * @param XmapDisplayerInterface $xmap
     * @param stdClass $parent
     * @param array $params
     * @param int $parent_id
     */
    private static function getCategoryTree($xmap, stdClass $parent, array &$params, $parent_id)
    {
        $db = JFactory::getDbo();

        $query = $db->getQuery(true)
            ->select(array('c.id', 'c.alias', 'c.title', 'c.parent_id'))
            ->from('#__phocagallery_categories AS c')
            ->where('c.parent_id = ' . $db->quote($parent_id))
            ->where('c.published = 1')
            ->order('c.ordering');

        if (!$params['show_unauth']) {
            $query->where('c.access IN(' . $params['groups'] . ')');
        }

        if ($params['language_filter']) {
            $query->where('c.language IN(' . $db->quote(JFactory::getLanguage()->getTag()) . ', ' . $db->quote('*') . ')');
        }

        $db->setQuery($query);
        $rows = $db->loadObjectList();

        if (empty($rows)) {
            return;
        }

        $xmap->changeLevel(1);

        foreach ($rows as $row) {
            $node = new stdclass;
            $node->id = $parent->id;
            $node->name = $row->title;
            $node->uid = $parent->uid . '_cid_' . $row->id;
            $node->browserNav = $parent->browserNav;
            $node->priority = $params['category_priority'];
            $node->changefreq = $params['category_changefreq'];
            $node->pid = $row->parent_id;
            $node->link = PhocaGalleryRoute::getCategoryRoute($row->id, $row->alias);

            if ($xmap->printNode($node) !== false) {
                self::getCategoryTree($xmap, $parent, $params, $row->id);
                if ($params['include_images']) {
                    self::getImages($xmap, $parent, $params, $row->id, $row->alias);
                }
            }
        }

        $xmap->changeLevel(-1);
    }

    /**
     * @param XmapDisplayerInterface $xmap
     * @param stdClass $parent
     * @param array $params
     * @param int $catid
     * @param string $catAlias
     */
    private static function getImages($xmap, stdClass $parent, array &$params, $catid, $catAlias)
    {
        $db = JFactory::getDbo();

        $query = $db->getQuery(true)
            ->select(array('g.id', 'g.alias', 'g.title', 'g.filename'))
            ->from('#__phocagallery AS g')
            ->where('g.catid = ' . $db->Quote($catid))
            ->where('g.published = 1')
            ->order('g.ordering');

        if ($params['language_filter']) {
            $query->where('g.language IN(' . $db->quote(JFactory::getLanguage()->getTag()) . ', ' . $db->quote('*') . ')');
        }

        $db->setQuery($query);
        $rows = $db->loadObjectList();

        if (empty($rows)) {
            return;
        }

        $xmap->changeLevel(1);

        $root = JUri::root() . 'images/phocagallery/';

        foreach ($rows as $row) {
            $node = new stdclass;
            $node->id = $parent->id;
            $node->name = $row->title;
            $node->uid = $parent->uid . '_' . $row->id;
            $node->browserNav = $parent->browserNav;
            $node->priority = $params['image_priority'];
            $node->changefreq = $params['image_changefreq'];
            $node->link = PhocaGalleryRoute::getImageRoute($row->id, $catid, $row->alias, $catAlias);

            if ($params['enable_imagemap']) {
                $node->isImages = 1;
                // $node->images can be a array with more than one image
                $node->images[0] = new stdClass;
                $node->images[0]->src = $root . self::setImageSrc($row->filename, $params);
                $node->images[0]->title = $row->title;
            }

            $xmap->printNode($node);
        }

        $xmap->changeLevel(-1);
    }

    private static function setImageSrc($filename, array &$params)
    {
        $path = '';
        if (strpos($filename, '/') !== false) {
            $path = explode('/', $filename);
            $filename = array_pop($path);
            $path = implode('/', $path) . '/';
        }

        switch ($params['image_type']) {
            case'thumb_s':
                $path .= 'thumbs/phoca_thumb_s_' . $filename;
                break;

            case'thumb_m':
                $path .= 'thumbs/phoca_thumb_m_' . $filename;
                break;

            case'thumb_l':
                $path .= 'thumbs/phoca_thumb_l_' . $filename;
                break;
            default:
                $path .= $filename;
                break;
        }

        return $path;
    }
}