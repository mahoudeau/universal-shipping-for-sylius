<?php

declare(strict_types=1);

namespace Mahoudeau\UniversalShipping\Controller\Admin;

use Doctrine\Persistence\ObjectManager;
use Doctrine\Persistence\ObjectRepository;
use Mahoudeau\UniversalShipping\Label\LabelException;
use Mahoudeau\UniversalShipping\Label\LabelManager;
use Mahoudeau\UniversalShipping\Model\ParcelStatus;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Sylius\Component\Core\Model\ShipmentInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * The label buttons on the admin order page. Behind the admin firewall, like every
 * route imported under the admin prefix.
 */
final readonly class LabelController
{
    public const CSRF_TOKEN_ID = 'universal_shipping_label';

    /** @param ObjectRepository<object> $shipments */
    public function __construct(
        private ObjectRepository $shipments,
        private ObjectManager $manager,
        private LabelManager $labels,
        private CsrfTokenManagerInterface $csrfTokens,
        private UrlGeneratorInterface $urls,
        private LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function create(Request $request, int $id): Response
    {
        $shipment = $this->shipment($id);
        $this->checkCsrf($request, $shipment);

        try {
            $parcel = $this->labels->create($shipment);
            $this->manager->flush();
            $this->flash($request, 'success', 'universal_shipping.label.created', ['%tracking%' => $parcel->trackingNumber ?? '']);
        } catch (LabelException $exception) {
            $this->logger->warning('Label for shipment {id} not created: {error}', ['id' => $id, 'error' => $exception->getMessage()]);
            $this->flash($request, 'error', 'universal_shipping.label.not_created', ['%error%' => $exception->getMessage()]);
        }

        return $this->backToOrder($shipment);
    }

    public function download(int $id): Response
    {
        $shipment = $this->shipment($id);

        try {
            $pdf = $this->labels->label($shipment);
        } catch (LabelException $exception) {
            return new Response($exception->getMessage(), Response::HTTP_BAD_GATEWAY, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        return new Response($pdf, Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('inline; filename="label-%s-%d.pdf"', preg_replace('/[^A-Za-z0-9_-]/', '', (string) $shipment->getOrder()?->getNumber()), $id),
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function cancel(Request $request, int $id): Response
    {
        $shipment = $this->shipment($id);
        $this->checkCsrf($request, $shipment);

        try {
            $parcel = $this->labels->cancel($shipment);
            $this->manager->flush();
            $this->flash($request, 'success', ParcelStatus::Cancelled === $parcel->status ? 'universal_shipping.label.cancelled' : 'universal_shipping.label.cancelling');
        } catch (LabelException $exception) {
            $this->flash($request, 'error', 'universal_shipping.label.not_cancelled', ['%error%' => $exception->getMessage()]);
        }

        return $this->backToOrder($shipment);
    }

    private function shipment(int $id): ShipmentInterface
    {
        $shipment = $this->shipments->find($id);
        if (!$shipment instanceof ShipmentInterface) {
            throw new NotFoundHttpException(sprintf('Shipment %d not found.', $id));
        }

        return $shipment;
    }

    private function checkCsrf(Request $request, ShipmentInterface $shipment): void
    {
        $token = new CsrfToken(self::CSRF_TOKEN_ID . $shipment->getId(), (string) $request->request->get('_csrf_token'));

        if (!$this->csrfTokens->isTokenValid($token)) {
            throw new AccessDeniedHttpException('Invalid CSRF token.');
        }
    }

    /** @param array<string, string> $parameters */
    private function flash(Request $request, string $type, string $message, array $parameters = []): void
    {
        $session = $request->getSession();
        if ($session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->add($type, ['message' => $message, 'parameters' => $parameters]);
        }
    }

    private function backToOrder(ShipmentInterface $shipment): RedirectResponse
    {
        return new RedirectResponse($this->urls->generate('sylius_admin_order_show', ['id' => $shipment->getOrder()?->getId()]));
    }
}
