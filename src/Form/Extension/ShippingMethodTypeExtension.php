<?php

declare(strict_types=1);

namespace Mahoudeau\UniversalShipping\Form\Extension;

use Mahoudeau\UniversalShipping\DeliveryOption\DeliveryOptionRegistry;
use Sylius\Bundle\ShippingBundle\Form\Type\ShippingMethodType;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Lets the admin link a Sylius shipping method to a configured delivery option.
 */
final class ShippingMethodTypeExtension extends AbstractTypeExtension
{
    public function __construct(private readonly DeliveryOptionRegistry $deliveryOptions)
    {
    }

    public static function getExtendedTypes(): iterable
    {
        return [ShippingMethodType::class];
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $choices = [];
        foreach ($this->deliveryOptions->all() as $option) {
            $choices[$option->label] = $option->code;
        }

        $builder->add('deliveryOptionCode', ChoiceType::class, [
            'required' => false,
            'choices' => $choices,
            'choice_translation_domain' => false,
            'placeholder' => 'universal_shipping.admin.none',
            'label' => 'universal_shipping.admin.delivery_option',
            'help' => 'universal_shipping.admin.delivery_option_help',
        ]);
    }
}
