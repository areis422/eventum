<?php

/*
 * This file is part of the Eventum (Issue Tracking System) package.
 *
 * @copyright (c) Eventum Team
 * @license GNU General Public License, version 2 or later (GPL-2+)
 *
 * For the full copyright and license information,
 * please see the COPYING and AUTHORS files
 * that were distributed with this source code.
 */

namespace Eventum\Console\Command;

use Eventum\Db\Doctrine;
use Eventum\Export\IssueExport;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ExportIssuesCommand extends BaseCommand
{
    public const DEFAULT_COMMAND = 'export:issues';

    protected static $defaultName = 'eventum:' . self::DEFAULT_COMMAND;

    protected function configure(): void
    {
        $this
            ->addArgument('issueId', InputArgument::REQUIRED)
            ->addArgument('fileName', InputArgument::REQUIRED);
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $issueId = $input->getArgument('issueId');
        $fileName = $input->getArgument('fileName');

        if ($issueId) {
            $this->exportIssue($issueId, $fileName ?: 'output.csv');
        }

        return 0;
    }

    private function exportIssue(int $issueId, string $fileName): void
    {
        $repo = Doctrine::getIssueRepository();
        $issue = $repo->findById($issueId);

        $exporter = new IssueExport($fileName);
        $exporter->export($issue);
    }
}
