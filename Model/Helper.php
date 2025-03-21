<?php
/**
 * Tweakwise (https://www.tweakwise.com/) - All Rights Reserved
 *
 * @copyright Copyright (c) 2017-2022 Tweakwise.com B.V. (https://www.tweakwise.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Tweakwise\Magento2TweakwiseExport\Model;

use DateTime;
use IntlDateFormatter;
use SplFileInfo;
use Magento\Framework\App\ProductMetadata as CommunityProductMetadata;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;

class Helper
{
    /**
     * @var ProductMetadataInterface
     */
    protected $productMetadata;

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var TimezoneInterface
     */
    protected $localDate;

    /**
     * Helper constructor.
     *
     * @param ProductMetadataInterface $productMetadata
     * @param Config $config
     * @param TimezoneInterface $localDate
     */
    public function __construct(
        ProductMetadataInterface $productMetadata,
        Config $config,
        TimezoneInterface $localDate
    ) {
        $this->productMetadata = $productMetadata;
        $this->config = $config;
        $this->localDate = $localDate;
    }

    /**
     * @param int $storeId
     * @param int $entityId
     * @return string
     */
    public function getTweakwiseId(int $storeId, int $entityId): string
    {
        if (!$storeId) {
            return $entityId;
        }
        // Prefix 1 is to make sure it stays the same length when casting to int
        return '1' . str_pad($storeId, 4, '0', STR_PAD_LEFT) . $entityId;
    }

    /**
     * @param int $id
     *
     * @return int
     */
    public function getStoreId(int $id): int
    {
        return (int)substr($id, 5);
    }

    /**
     * @return bool
     */
    public function isEnterprise(): bool
    {
        return $this->productMetadata->getEdition()
            !== CommunityProductMetadata::EDITION_NAME;
    }

    /**
     * Get start date of current feed export. Only working with export to file.
     *
     * @return DateTime|null
     */
    public function getFeedExportStartDate(): ?DateTime
    {
        $file = new SplFileInfo($this->config->getFeedTmpFile());
        if (!$file->isFile()) {
            return null;
        }

        return new DateTime('@' . $file->getMTime());
    }

    /**
     * Get date of last finished feed export
     *
     * @return DateTime|null
     */
    public function getLastFeedExportDate(): ?DateTime
    {
        $file = new SplFileInfo($this->config->getDefaultFeedFile());
        if (!$file->isFile()) {
            return null;
        }

        return new DateTime('@' . $file->getMTime());
    }

    /**
     * @return \Magento\Framework\Phrase|string
     */
    public function getExportStateText()
    {
        $startDate = $this->getFeedExportStartDate();
        if (!$this->config->isRealTime() && $startDate) {
            return sprintf(
                __('Running, started on %s.'),
                $this->localDate->formatDate(
                    $startDate,
                    IntlDateFormatter::MEDIUM,
                    true
                )
            );
        }

        $finishedDate = $this->getLastFeedExportDate();
        if ($finishedDate) {
            return sprintf(
                __('Finished on %s.'),
                $this->localDate->formatDate(
                    $finishedDate,
                    IntlDateFormatter::MEDIUM,
                    true
                )
            );
        }

        return __('Export never triggered.');
    }
}
