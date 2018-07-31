<?php
/**
 * Joomla! Content Management System
 *
 * @copyright  Copyright (C) 2005 - 2018 Open Source Matters, Inc. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Menus\Administrator\Field;


defined('JPATH_BASE') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\GroupedlistField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

/**
 * Supports an HTML grouped select list of menu item grouped by menu
 *
 * @since  4.0.0
 */
class MenuItemByViewTypeField extends GroupedlistField
{
    /**
     * Method to get the field option groups.
     *
     * @return  array  The field option objects as a nested array in groups.
     *
     * @since   3.8.0
     */
    protected function getGroups()
    {
        $language = Factory::getLanguage();
        $app       = \JFactory::getApplication();
        $input     = $app->input;
        $filters = $input->getInputForRequestMethod("filter") != null? $input->getInputForRequestMethod("filter"): array() ;
        $client_id = $input->getInputForRequestMethod("client_id")!= null? $input->getInputForRequestMethod("client_id"):0;
        $menutype = $input->getInputForRequestMethod("menutype")!=null? $input->getInputForRequestMethod("menutype"):null;

        $groups = array();
        $db    = Factory::getDbo();
        $query = $db->getQuery(true);
        $query->select(" DISTINCT a.name as name, a.extension_id as exid,b.link as link");
        $query->from("#__extensions as a")->innerJoin(" `#__menu` as b on a.extension_id = b.component_id");
        $query->innerJoin(" `#__menu_types` as c on b.menutype = c.menutype ");
        $query->where(" b.type like 'component'");
        $query->where("c.client_id =" . $client_id);
        if(!empty($menutype)){
            $query->where("b.menutype = '$menutype'");
        }
        if(!empty($filters)){
            foreach ($filters as $filtkey => $filtval){
                if(!empty($filtval)){
                    switch ($filtkey){
                        case "access" :
                        {

                            $query->where("b.access = " . $filtval);
                        }

                        case "published":{

                            $query->where("b.published = " . $filtval);
                        }
                    }
                }
            }

        }
        $extensionsname = $db->setQuery($query)->loadObjectList();
        foreach ($extensionsname as $menu)
        {
            $language->load($menu->name . '.sys', JPATH_ADMINISTRATOR, null, false, true)
            || $language->load($menu->name . '.sys', JPATH_ADMINISTRATOR . '/components/' . $menu->name, null, false, true);

            $viewarray = array();
            parse_str($menu->link, $viewarray);
            if (isset($viewarray['view']))
            {
                $viewarray['layout'] = $viewarray['layout'] ?? 'default';
                if (strpos($viewarray['layout'], ':') > 0)
                {
                    // Use template folder for layout file
                    $temp = explode(':', $viewarray['layout']);
                    $file = JPATH_SITE . '/templates/' . $temp[0] . '/html/' . $menu->name . '/' . $viewarray['view'] . '/' . $temp[1] . '.xml';
                    if (!file_exists($file))
                    {
                        $file = JPATH_SITE . '/components/' . $menu->name . '/views/' . $viewarray['view'] . '/tmpl/' . $temp[1] . '.xml';
                    }else{
                        $file = JPATH_SITE . '/components/' . $menu->name . '/view/' . $viewarray['view'] . '/tmpl/' . $temp[1] . '.xml';

                    }

                }
                else
                {
                    // Get XML file from component folder for standard layouts
                    $file = JPATH_SITE . '/components/' . $menu->name . '/tmpl/' . $viewarray['view'] . '/' . $viewarray['layout'] . '.xml';

                    if (!file_exists($file))
                    {
                        $file = JPATH_SITE . '/components/' . $menu->name . '/view/' . $viewarray['view'] . '/tmpl/' . $viewarray['layout'] . '.xml';
                    }
                }

                if (is_file($file) && $xml = simplexml_load_file($file))
                {
                    // Look for the first view node off of the root node.
                    if ($layout = $xml->xpath('layout[1]'))
                    {
                        if (!empty($layout[0]['title']))
                        {
                            $viewTranslate = Text::_(trim((string) $layout[0]['title']));
                        }
                    }


                }

                unset($xml);


            }
            if(count($viewarray)>0){
                $groups[Text::_($menu->name)][] = HTMLHelper::_('select.option',
                    json_encode([$menu->exid,  $viewarray["view"]]),
                    $viewTranslate

                );
            }else{
                $groups[Text::_($menu->name)][] = HTMLHelper::_('select.option',
                    json_encode([ $menu->exid]),
                    Text::_($menu->name)

                );
            }


        }



        $groups = array_merge(parent::getGroups(), $groups);

        return $groups;
    }

}