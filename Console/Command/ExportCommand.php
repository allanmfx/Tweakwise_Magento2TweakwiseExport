<?php
/**
 * Tweakwise (https://www.tweakwise.com/) - All Rights Reserved
 *
 * @copyright Copyright (c) 2017-2022 Tweakwise.com B.V. (https://www.tweakwise.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Tweakwise\Magento2TweakwiseExport\Console\Command;

use Tweakwise\Magento2TweakwiseExport\Model\Config;
use Tweakwise\Magento2TweakwiseExport\Model\Export;
use Tweakwise\Magento2TweakwiseExport\Profiler\Driver\ConsoleDriver;
use Exception;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Profiler;
use Magento\Store\Model\StoreManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ExportCommand extends Command
{
    /**
     * @var Config
     */
    protected $config;

    /**
     * @var Export
     */
    protected $export;

    /**
     * @var State
     */
    protected $state;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;
    /**
     * ExportCommand constructor.
     *
     * @param Config $config
     * @param Export $export
     * @param State $state
     */
    public function __construct(Config $config, Export $export, State $state, StoreManagerInterface $storeManager)
    {
        $this->config = $config;
        $this->export = $export;
        $this->state = $state;
        $this->storeManager = $storeManager;
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this->setName('tweakwise:export')
            ->addOption('file', 'f', InputArgument::OPTIONAL, 'Export to specific file')
            ->addOption('store', 's', InputArgument::OPTIONAL, 'Export specific store')
            ->addOption(
                'validate',
                'c',
                InputOption::VALUE_OPTIONAL, 'Validate feed and rollback if fails [y/n].'
            )
            ->addOption('debug', 'd', InputOption::VALUE_NONE, 'Debugging enables profiler.')
            ->setDescription('Export tweakwise feed');
    }

    /**
     * {@inheritdoc}
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->state->emulateAreaCode(Area::AREA_CRONTAB, function() use ($input, $output) {
            if ($input->getOption('debug')) {
                Profiler::enable();
                Profiler::add(new ConsoleDriver($output));
            }

            $isStoreLevelExportEnabled = $this->config->isStoreLevelExportEnabled();
            $storeCode = (string) $input->getOption('store');
            $store = null;

            $validate = (string)$input->getOption('validate');
            if ($validate !== 'y' && $validate !== 'n' && $validate !== "") {
                $output->writeln('Validate option can only contain y or n');

                return -1;
            }

            $validate = $validate === "" ? $this->config->isValidate() : $validate === 'y';
            $startTime = microtime(true);
            $feedFile = (string)$input->getOption('file');

            if ($isStoreLevelExportEnabled) {
                if (!$storeCode) {
                    $output->writeln('<error>Store level export enabled please provide --store <store-code></error>');

                    return -1;
                }

                try {
                    $store = $this->storeManager->getStore($storeCode);
                } catch (NoSuchEntityException $exception) {
                    $output->writeln('<error>Store does not exist</error>');

                    return -1;
                }

                if (!$this->config->isEnabled($store)) {
                    $output->writeln('<error>Tweakwise export does not enabled in this store</error>');

                    return -1;
                }

                if (!$feedFile) {
                    $feedFile = $this->config->getDefaultFeedFile($store);
                }

                $output->writeln("<info>generatig feed for {$store->getCode()}</info>");
                $this->export->generateToFile($feedFile, $validate, $store);
                $output->writeln("<info>feed file: {$feedFile}</info>");
            } else {
                if ($storeCode) {
                    $output->writeln('<error>Store level export disabled, remove --store parameter</error>');
                    return -1;
                }

                if (!$feedFile) {
                    $feedFile = $this->config->getDefaultFeedFile();
                }

                $output->writeln("<info>generating single feed for export enabled stores</info>");
                $this->export->generateToFile($feedFile, $validate);
                $output->writeln("<info>feed file: {$feedFile}</info>");
            }

            $generateTime = round(microtime(true) - $startTime, 2);
            $memoryUsage  = round(memory_get_peak_usage(true) / 1024 / 1024,
                2);
            $output->writeln(sprintf('Feed written to %s in %ss using %sMb memory',
                $feedFile, $generateTime, $memoryUsage));
        });
    }
}