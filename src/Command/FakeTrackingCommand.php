<?php

declare(strict_types=1);

namespace Mahoudeau\UniversalShipping\Command;

use Mahoudeau\UniversalShipping\Model\ParcelStatus;
use Mahoudeau\UniversalShipping\Tracking\ParcelTracker;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Plays the carrier for a fake label: moves the parcel along, as a tracking webhook would.
 * Only touches parcels made by the fake label provider.
 */
#[AsCommand(name: 'universal-shipping:fake-tracking', description: 'Move a fake parcel to another status, as a carrier webhook would')]
final class FakeTrackingCommand extends Command
{
    public function __construct(private readonly ParcelTracker $tracker)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $statuses = array_map(static fn (ParcelStatus $status): string => $status->value, ParcelStatus::cases());

        $this
            ->addArgument('parcel', InputArgument::REQUIRED, 'The parcel id, shown on the admin order page, e.g. fake-3f9a1c2b7e')
            ->addArgument('status', InputArgument::REQUIRED, implode(', ', $statuses))
            ->addArgument('message', InputArgument::OPTIONAL, 'What the carrier would say')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $status = ParcelStatus::tryFrom((string) $input->getArgument('status'));
        if (null === $status) {
            $output->writeln(sprintf('<error>Unknown status. Use one of: %s</error>', implode(', ', array_map(
                static fn (ParcelStatus $status): string => $status->value,
                ParcelStatus::cases(),
            ))));

            return Command::INVALID;
        }

        $message = $input->getArgument('message');
        $updated = $this->tracker->update(
            provider: 'fake',
            parcelId: (string) $input->getArgument('parcel'),
            status: $status,
            statusText: \is_string($message) ? $message : null,
            changedAt: microtime(true),
        );

        if (!$updated) {
            $output->writeln('<error>No fake parcel with this id.</error>');

            return Command::FAILURE;
        }

        $output->writeln(sprintf('Parcel is now <info>%s</info>.', $status->value));

        return Command::SUCCESS;
    }
}
