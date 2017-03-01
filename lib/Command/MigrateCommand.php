<?php
/**
 * @copyright Copyright (c) 2017 Robin Appelman <robin@icewind.nl>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Migration\Command;

use OC\Core\Command\Base;
use OCA\Files_Sharing\External\Manager;
use OCA\Migration\Migrator;
use OCA\Migration\Remote;
use OCP\Federation\ICloudIdManager;
use OCP\Files\IRootFolder;
use OCP\Http\Client\IClientService;
use OCP\IL10N;
use OCP\IURLGenerator;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

class MigrateCommand extends Base {
	private $cloudIdManager;
	private $clientService;
	private $rootFolder;
	private $externalShareManager;
	private $urlGenerator;
	private $l10n;

	function __construct(
		ICloudIdManager $cloudIdManager,
		IClientService $clientService,
		IRootFolder $rootFolder,
		Manager $externalShareManager,
		IURLGenerator $urlGenerator,
		IL10N $l10n
	) {
		parent::__construct();

		$this->cloudIdManager = $cloudIdManager;
		$this->clientService = $clientService;
		$this->rootFolder = $rootFolder;
		$this->externalShareManager = $externalShareManager;
		$this->urlGenerator = $urlGenerator;
		$this->l10n = $l10n;
	}

	protected function configure() {
		$this
			->setName('migration:migrate')
			->setDescription('Migrate a user from a different instance to this one')
			->addArgument(
				'target_user',
				InputArgument::REQUIRED,
				'The id of the local user to migrate to'
			)->addArgument(
				'source_cloud_id',
				InputArgument::REQUIRED,
				'The cloud id of the user to migrate to this instance'
			);
		parent::configure();
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$cloudId = $this->cloudIdManager->resolveCloudId($input->getArgument('source_cloud_id'));

		/** @var QuestionHelper $questionHelper */
		$questionHelper = $this->getHelper('question');
		$question = new Question($this->l10n->t('Password for %s: ', [$cloudId->getId()]));
		$question->setHidden(true);
		$question->setHiddenFallback(false);
		$password = $questionHelper->ask($input, $output, $question);

		$remote = new Remote($cloudId, $password, $this->clientService);
		$targetUserId = $input->getArgument('target_user');
		$targetUser = $this->cloudIdManager->getCloudId($targetUserId, $this->urlGenerator->getAbsoluteURL('/'));
		$userFolder = $this->rootFolder->getUserFolder($targetUserId);
		$migrator = new Migrator($remote, $userFolder, $this->clientService, $targetUser, $this->externalShareManager);

		/** @var ProgressBar $progressBar */
		$progressBar = null;

		$migrator->listen('Migrator', 'step', function ($step) use (&$progressBar, $output) {
			$output->writeln($this->formatStep($step));
			$progressBar = new ProgressBar($output);
		});

		$migrator->listen('Migrator', 'progress', function ($step, $progress) use ($progressBar) {
			$progressBar->advance();
		});

		$migrator->migrate();
	}

	private function formatStep($step) {
		switch ($step) {
			case 'files':
				return $this->l10n->t('Copying files');
			case 'federated_shares':
				return $this->l10n->t('Copying federated shares');
			default:
				return $this->l10n->t('Copying...');
		}
	}
}
