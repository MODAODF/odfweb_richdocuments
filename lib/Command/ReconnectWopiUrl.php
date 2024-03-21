<?php
namespace OCA\RichDocuments\Command;

use OCA\Richdocuments\AppConfig;
use OCA\Richdocuments\Service\CapabilitiesService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ReconnectWopiUrl extends Command {

 	/** @var AppConfig */
    private $appConfig;
	/** @var CapabilitiesService */
	private $capabilitiesService;

    public function __construct(AppConfig $appConfig,
								CapabilitiesService $capabilitiesService) {
		parent::__construct();
		$this->appConfig = $appConfig;
		$this->capabilitiesService = $capabilitiesService;
    }
	protected function configure() {
		$this
			->setName('richdocuments:reconnect-wopi-url')
			->setDescription('Try to reconnect if Wopi-URL is not activated');
	}
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$wopi_url = $this->appConfig->getAppValue('wopi_url');
		$wopi_url_keep = $this->appConfig->getAppValue('wopi_url_keep');
		if ($wopi_url) {
			$output->writeln('');
			$output->writeln('<comment>Wopi-URL is activating</comment>');
			$output->writeln('');
			return 0;
		} elseif (!$wopi_url && $wopi_url_keep) {
			$wopiStatus = $this->capabilitiesService->checkOnlineStatus($wopi_url_keep);
			if ($wopiStatus) {
				$this->appConfig->setAppValue('wopi_url', $wopi_url_keep);
				$this->appConfig->setAppValue('wopi_url_keep', '');
				$output->writeln('');
				$output->writeln('<info>Successful connection</info>');
				$output->writeln('');
				return 0;
			} else {
				$output->writeln('');
				$output->writeln('<comment>Failed to reconnect, could not establish connection</comment>');
				$output->writeln('');
				return 1;
			}
		} else {
			$output->writeln('');
			$output->writeln('<error>Failed to implement the function</error>');
			$output->writeln('');
			return 1;
		}
    }
}