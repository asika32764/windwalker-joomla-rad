<?php
/**
 * Part of Windwalker project. 
 *
 * @copyright  Copyright (C) 2011 - 2014 SMS Taiwan, Inc. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

use Joomla\Registry\Registry;
use Windwalker\Data\Data;
use Windwalker\Helper\DateHelper;
use Windwalker\View\Html\ItemHtmlView;
use Windwalker\View\Html\ListHtmlView;

/**
 * Class {{extension.name.cap}}View{{controller.item.name.cap}}
 *
 * @since 1.0
 */
class {{extension.name.cap}}View{{controller.item.name.cap}}Html extends ItemHtmlView
{
	/**
	 * Property prefix.
	 *
	 * @var  string
	 */
	protected $prefix = '{{extension.name.lower}}';

	/**
	 * Property option.
	 *
	 * @var  string
	 */
	protected $option = '{{extension.element.lower}}';

	/**
	 * Property textPrefix.
	 *
	 * @var string
	 */
	protected $textPrefix = '{{extension.element.upper}}';

	/**
	 * Property viewItem.
	 *
	 * @var  string
	 */
	protected $viewItem = '{{controller.item.name.lower}}';

	/**
	 * Property viewList.
	 *
	 * @var  string
	 */
	protected $viewList = '{{controller.list.name.lower}}';

	/**
	 * prepareData
	 *
	 * @return  void
	 */
	protected function prepareData()
	{
		$data = $this->getData();
		$user = $this->container->get('user');

		$data->category = $this->get('Category');
		$data->params   = $this->get('Params');

		// Prepare setting data
		$item = $data->item = new Data($data->item);

		// Link
		// =====================================================================================
		$item->link = new JURI("index.php?option={{extension.element.lower}}&view={{controller.item.name.lower}}&id={$item->id}");
		$item->link->setVar('alias', $item->alias);
		$item->link->setVar('catid', $item->catid);
		$item->link = JRoute::_((string) $item->link);

		// Dsplay Data
		// =====================================================================================
		$item->created_user = JFactory::getUser($item->created_by)->get('name');
		$item->cat_title = !empty($this->category) ? $this->category->title : null;

		if ($item->modified == '0000-00-00 00:00:00')
		{
			$item->modified = '';
		}

		// View Level
		// =====================================================================================
		if ($access = $data->state->get('filter.access'))
		{
			// If the access filter has been set, we already know this user can view.
			$data->params->set('access-view', true);
		}
		else
		{
			// If no access filter is set, the layout takes some responsibility for display of limited information.
			$user   = JFactory::getUser();
			$groups = $user->getAuthorisedViewLevels();

			if (!$item->catid || empty($this->category->access))
			{
				$data->params->set('access-view', in_array($item->access, $groups));
			}
			else
			{
				$data->params->set('access-view', in_array($item->access, $groups) && in_array($this->category->access, $groups));
			}
		}

		// Publish Date
		// =====================================================================================
		$pup  = DateHelper::getDate($item->publish_up)->toUnix(true);
		$pdw  = DateHelper::getDate($item->publish_down)->toUnix(true);
		$now  = DateHelper::getDate('now')->toUnix(true);
		$null = DateHelper::getDate('0000-00-00 00:00:00')->toUnix(true);

		if (($now < $pup && $pup != $null) || ($now > $pdw && $pdw != $null))
		{
			$item->published = 0;
		}

		$this->prepareEvents($item);

		$this->configureParams($item);
	}

	/**
	 * prepareEvents
	 *
	 * @param $item
	 *
	 * @return  void
	 */
	protected function prepareEvents($item)
	{
		$data = $this->getData();

		// Plugins
		// =====================================================================================
		$item->event = new stdClass;

		$dispatcher = $this->container->get('event.dispatcher');
		JPluginHelper::importPlugin('content');

		$item->text = $item->introtext . $item->fulltext;
		$results = $dispatcher->trigger('onContentPrepare', array('{{extension.element.lower}}.{{controller.item.name.lower}}', &$item, &$data->params, 0));

		$results = $dispatcher->trigger('onContentAfterTitle', array('{{extension.element.lower}}.{{controller.item.name.lower}}', &$item, &$data->params, 0));
		$item->event->afterDisplayTitle = trim(implode("\n", $results));

		$results = $dispatcher->trigger('onContentBeforeDisplay', array('{{extension.element.lower}}.{{controller.item.name.lower}}', &$item, &$data->params, 0));
		$item->event->beforeDisplayContent = trim(implode("\n", $results));

		$results = $dispatcher->trigger('onContentAfterDisplay', array('{{extension.element.lower}}.{{controller.item.name.lower}}', &$item, &$data->params, 0));
		$item->event->afterDisplayContent = trim(implode("\n", $results));
	}

	/**
	 * configureParams
	 *
	 * @param $item
	 *
	 * @return  void
	 */
	protected function configureParams($item)
	{
		$app  = $this->container->get('app');
		$data = $this->getData();

		// Params
		// =====================================================================================

		// Merge {{controller.item.name.lower}} params. If this is single-{{controller.item.name.lower}} view, menu params override article params
		// Otherwise, {{controller.item.name.lower}} params override menu item params
		$active       = $app->getMenu()->getActive();
		$temp         = clone ($data->params);
		$item->params = new JRegistry($item->params);

		// Check to see which parameters should take priority
		if ($active)
		{
			$currentLink = $active->link;

			// If the current view is the active item and an {{controller.item.name.lower}} view for this {{controller.item.name.lower}},
			// then the menu item params take priority
			if (strpos($currentLink, 'view={{controller.item.name.lower}}') && (strpos($currentLink, '&id=' . (string) $item->id)))
			{
				// $item->params are the {{controller.item.name.lower}} params, $temp are the menu item params
				// Merge so that the menu item params take priority
				$item->params->merge($temp);

				// Load layout from active query (in case it is an alternative menu item)
				if (isset($active->query['layout']))
				{
					$this->setLayout($active->query['layout']);
				}
			}
			else
			{
				// Current view is not a single {{controller.item.name.lower}}, so the {{controller.item.name.lower}} params take priority here
				// Merge the menu item params with the {{controller.item.name.lower}} params so that the {{controller.item.name.lower}} params take priority
				$temp->merge($item->params);
				$this->params = $temp;

				// Check for alternative layouts (since we are not in a single-{{controller.item.name.lower}} menu item)
				// Single-{{controller.item.name.lower}} menu item layout takes priority over alt layout for an {{controller.item.name.lower}}
				if ($layout = $data->params->get('{{controller.item.name.lower}}_layout'))
				{
					$this->setLayout($layout);
				}

				// If not Active, set Title
				$this->setTitle($item->get('title'));
			}
		}
		else
		{
			// Merge so that article params take priority
			$temp->merge($data->params);
			$this->params = $temp;

			// Check for alternative layouts (since we are not in a single-article menu item)
			// Single-article menu item layout takes priority over alt layout for an article
			if ($layout = $data->params->get('{{controller.item.name.lower}}_layout'))
			{
				$this->setLayout($layout);
			}

			// If not Active, set Title
			$this->setTitle($item->get('title'));
		}

		$item->params = $data->params;
	}
}