<?php
/**
 * Part of Windwalker project.
 *
 * @copyright  Copyright (C) 2014 {ORGANIZATION}. All rights reserved.
 * @license    GNU General Public License version 2 or later;
 */

namespace Windwalker\DataMapper;

use Joomla\DI\Container;
use Windwalker\DataMapper\Adapter\DatabaseAdapter;
use Windwalker\DI\ServiceProvider;
use Windwalker\DataMapper\Adapter\JoomlaAdapter;

/**
 * The DataMapperProvider class.
 * 
 * @since  {DEPLOY_VERSION}
 */
class DataMapperProvider extends ServiceProvider
{
	/**
	 * Registers the service provider with a DI container.
	 *
	 * @param   Container $container The DI container.
	 *
	 * @return  Container  Returns itself to support chaining.
	 *
	 * @since   1.0
	 */
	public function register(Container $container)
	{
		DatabaseAdapter::setInstance(new JoomlaAdapter($container->get('db')));
	}
}
