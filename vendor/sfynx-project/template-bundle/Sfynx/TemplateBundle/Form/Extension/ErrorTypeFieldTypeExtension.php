<?php
/**
 * This file is part of the <Template> project.
 *
 * @subpackage   Extension
 * @package    Form
 * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
 * @since 2012-01-09
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Sfynx\TemplateBundle\Form\Extension;

use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\FormBuilderInterface;

use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Symfony\Component\OptionsResolver\Options;

/**
 * ErrorTypeField Extension
 *
 * @subpackage   Extension
 * @package    Form
 * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
 */
class ErrorTypeFieldTypeExtension extends AbstractTypeExtension
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->setAttribute('field_error_type', $options['field_error_type']);
        $builder->setAttribute('error_delay', $options['error_delay']);
    }
    
    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        $view->vars['field_error_type'] = $form->getConfig()->getAttribute('field_error_type');
        $view->vars['error_delay'] = $form->getConfig()->getAttribute('error_delay');
    }
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'field_error_type' => false,
            'error_delay'=>false
        ));
    }
    public function getExtendedType()
    {
        return 'field';
    }
}