<?php

/**
 * This file is part of the <Encrypt> project.
 *
 * @category   Encrypt
 * @package    Builder
 * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
 * @since 2014-06-02
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Sfynx\EncryptBundle\Builder;

/**
 * PiEncryptorInterface interface.
 *
 * @category   Encrypt
 * @package    Builder
 * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
 */
interface PiEncryptorInterface {

    /**
     * options for encryption 
     */
    public function __construct(array $options);

    /**
     * @param string $data Plain text to encrypt
     * @return string Encrypted text
     */
    public function encrypt($data);

    /**
     * @param string $data Encrypted text
     * @return string Plain text
     */
    public function decrypt($data);
}