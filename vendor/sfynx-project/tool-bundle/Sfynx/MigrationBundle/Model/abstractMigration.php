<?php
/**
 * This file is part of the <Migration> project.
 *
 * @category   Sfynx
 * @package    Abstract
 * @subpackage MigrationModel
 * @author     Etienne de Longeaux <etienne.delongeaux@gmail.com>
 * @copyright  2015 PI6GROUPE
 * @license    http://opensource.org/licenses/gpl-license.php GNU Public License
 * @version    2.3
 * @link       http://opensource.org/licenses/gpl-license.php
 * @since      2015-02-16
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Sfynx\MigrationBundle\Model;

/**
 * Abstract model of a migration file.
 *
 * @category   Sfynx
 * @package    Abstract
 * @subpackage MigrationModel
 * @author     Etienne de Longeaux <etienne.delongeaux@gmail.com>
 * @copyright  2015 PI6GROUPE
 * @license    http://opensource.org/licenses/gpl-license.php GNU Public License
 * @version    2.3
 * @link       http://opensource.org/licenses/gpl-license.php
 * @since      2015-02-16
 */
abstract class abstractMigration
{
    protected $container;  
    
    /** @var \Symfony\Component\Console\Output\OutputInterface */
    protected $output;

    /** @var \Symfony\Component\Console\Helper\DialogHelper */
    protected $dialog;

    protected $basePath;
    
    protected $manager;    

    public function __construct($container, $basePath, $output, $dialog)
    {
        $this->container = $container;
        $this->basePath = $basePath;
        $this->output = $output;
        $this->dialog = $dialog;

        if ($this->test()) {
            $this->PreUp();
            $this->Up();
            $this->PostUp();
        }
    }

    protected function test()
    {
        return true;
    }

    protected function PreUp()
    {
        // do something
    }

    abstract protected function Up();

    protected function PostUp()
    {
        // do something
    }

    protected function log($msg, $test = null)
    {
        if (is_null($test)) {
            $this->output->writeln("  $msg");
        } elseif ($test) {
            $this->output->writeln("  $msg <info>[OK]</info>");
        } else {
            $this->output->writeln("  $msg <error>[KO]</error>");
        }
    }
}
