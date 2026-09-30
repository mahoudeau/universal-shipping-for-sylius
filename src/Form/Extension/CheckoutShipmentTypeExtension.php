<?php

declare(strict_types=1);

namespace Mahoudeau\UniversalShipping\Form\Extension;

use Mahoudeau\UniversalShipping\DeliveryOption\DeliveryOption;
use Mahoudeau\UniversalShipping\DeliveryOption\DeliveryOptionRegistry;
use Mahoudeau\UniversalShipping\Model\PickupPoint;
use Mahoudeau\UniversalShipping\Model\PickupPointAwareInterface;
use Mahoudeau\UniversalShipping\Provider\PickupPointFinder;
use Mahoudeau\UniversalShipping\Provider\PickupPointQuery;
use Sylius\Bundle\CoreBundle\Form\Type\Checkout\ShipmentType;
use Sylius\Component\Core\Model\ShippingMethodInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Adds a pickup point picker to each shipment of the checkout's shipping step.
 *
 * Sylius renders that step as a live component, so choosing a method or typing an
 * address re-renders the form server-side: no JavaScript here. The list of points
 * is always built on the server; the browser only ever sends back an id from it.
 */
final class CheckoutShipmentTypeExtension extends AbstractTypeExtension
{
    public const SEARCH_FIELD = 'pickupPointSearch';

    public const POINT_FIELD = 'pickupPoint';

    /**
     * @param RepositoryInterface<ShippingMethodInterface> $shippingMethodRepository
     */
    public function __construct(
        private readonly DeliveryOptionRegistry $deliveryOptions,
        private readonly PickupPointFinder $finder,
        private readonly RepositoryInterface $shippingMethodRepository,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public static function getExtendedTypes(): iterable
    {
        return [ShipmentType::class];
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // After Sylius adds the "method" field (priority 0).
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event): void {
            $shipment = $event->getData();
            if (!$shipment instanceof PickupPointAwareInterface) {
                return;
            }

            $this->addPickupPointFields(
                $event->getForm(),
                $shipment,
                $this->deliveryOptions->forShippingMethod($shipment->getMethod()),
                '',
            );
        }, -10);

        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event): void {
            $shipment = $event->getForm()->getData();
            $data = $event->getData();
            if (!$shipment instanceof PickupPointAwareInterface || !\is_array($data)) {
                return;
            }

            $method = isset($data['method']) ? $this->shippingMethodRepository->findOneBy(['code' => $data['method']]) : null;

            $this->addPickupPointFields(
                $event->getForm(),
                $shipment,
                $this->deliveryOptions->forShippingMethod($method),
                trim((string) ($data[self::SEARCH_FIELD] ?? '')),
            );
        });

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event): void {
            $shipment = $event->getData();
            if (!$shipment instanceof PickupPointAwareInterface) {
                return;
            }

            $option = $this->deliveryOptions->forShippingMethod($shipment->getMethod());
            $form = $event->getForm();

            if (null === $option || !$option->needsPickupPoint() || !$form->has(self::POINT_FIELD)) {
                $shipment->setPickupPoint(null);

                return;
            }

            $selected = $form->get(self::POINT_FIELD)->getData();
            if (!$selected instanceof PickupPoint) {
                $shipment->setPickupPoint(null);
                $form->get(self::POINT_FIELD)->addError(new FormError(
                    $this->translator->trans('universal_shipping.pickup_point.required', [], 'validators'),
                ));

                return;
            }

            $shipment->setPickupPoint($selected->withoutDistance());
        });
    }

    private function addPickupPointFields(
        FormInterface $form,
        PickupPointAwareInterface $shipment,
        ?DeliveryOption $option,
        string $search,
    ): void {
        if (null === $option || !$option->needsPickupPoint()) {
            $form->remove(self::SEARCH_FIELD);
            $form->remove(self::POINT_FIELD);

            return;
        }

        $address = $shipment->getOrder()?->getShippingAddress() ?? $shipment->getOrder()?->getBillingAddress();
        $query = match (true) {
            '' !== $search => new PickupPointQuery((string) ($address?->getCountryCode() ?? 'FR'), $search),
            null !== $address => PickupPointQuery::fromAddress($address),
            default => null,
        };

        $points = null === $query ? [] : $this->finder->search($query, $option);
        $current = $this->currentPoint($shipment, $option, $points);
        if (null !== $current) {
            // Keep a point chosen earlier selectable, even when a new search does not return it.
            $points = [$current, ...array_filter($points, static fn (PickupPoint $point): bool => $point->id !== $current->id)];
        }

        $form->add(self::SEARCH_FIELD, TextType::class, [
            'mapped' => false,
            'required' => false,
            'data' => $search,
            'label' => 'universal_shipping.pickup_point.search',
            'attr' => [
                'placeholder' => 'universal_shipping.pickup_point.search_placeholder',
                // A search, not the customer's own postcode: the browser's list would cover
                // the address suggestions (public/address-autocomplete.js).
                'autocomplete' => 'off',
                // For public/pickup-point-search.js and the address suggestions.
                'data-us-search-field' => '',
                'data-us-country' => (string) ($address?->getCountryCode() ?? 'FR'),
            ],
        ]);

        $form->add(self::POINT_FIELD, ChoiceType::class, [
            'mapped' => false,
            'required' => false,
            'expanded' => true,
            'multiple' => false,
            'placeholder' => false,
            'choices' => $points,
            'choice_value' => static fn (?PickupPoint $point): ?string => $point?->id,
            'choice_label' => static fn (PickupPoint $point): string => $point->name,
            'choice_translation_domain' => false,
            'data' => $current,
            'label' => 'universal_shipping.pickup_point.label',
            'invalid_message' => 'universal_shipping.pickup_point.invalid',
        ]);
    }

    /**
     * The point already saved on the shipment, confirmed with the carrier:
     * it may have closed since the customer chose it.
     *
     * @param list<PickupPoint> $searchResults
     */
    private function currentPoint(PickupPointAwareInterface $shipment, DeliveryOption $option, array $searchResults): ?PickupPoint
    {
        $saved = $shipment->getPickupPoint();
        if (null === $saved || $saved->provider !== $option->provider || $saved->carrier !== $option->carrier) {
            return null;
        }

        foreach ($searchResults as $point) {
            if ($point->id === $saved->id) {
                return $point;
            }
        }

        return $this->finder->find($saved->id, $option);
    }
}
