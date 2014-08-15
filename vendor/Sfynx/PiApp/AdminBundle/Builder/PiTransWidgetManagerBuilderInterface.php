<?php
/**
 * This file is part of the <Admin> project.
 *
 * @category   PiApp
 * @package    Builder
 * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
 * @since 2012-01-18
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace PiApp\AdminBundle\Builder;

/**
 * PiTransWidgetManagerBuilderInterface interface.
 *
 * @category   PiApp
 * @package    Builder
 * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
 */
interface PiTransWidgetManagerBuilderInterface
{
    public function renderSource($id, $lang = '', $params = null);
}