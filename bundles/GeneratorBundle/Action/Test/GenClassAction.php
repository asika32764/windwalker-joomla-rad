<?php
/**
 * Part of Windwalker project. 
 *
 * @copyright  Copyright (C) 2015 {ORGANIZATION}. All rights reserved.
 * @license    GNU General Public License version 2 or later;
 */

namespace GeneratorBundle\Action\Test;

use GeneratorBundle\Action\AbstractAction;
use Muse\Filesystem\Folder;
use Windwalker\String\String;

/**
 * The GenClassAction class.
 * 
 * @since  {DEPLOY_VERSION}
 */
class GenClassAction extends AbstractAction
{
	/**
	 * Do this execute.
	 *
	 * @return  mixed
	 */
	protected function doExecute()
	{
		$tmpl = file_get_contents(GENERATOR_BUNDLE_PATH . '/Template/test/testClass.php');

		$file = String::parseVariable($tmpl, $this->replace);

		Folder::create(dirname($this->config['replace.test.class.file']));

		file_put_contents($this->config['replace.test.class.file'], $file);
	}
}
