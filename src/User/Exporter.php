<?php

namespace Macareux\CsvUserImportExport\User;

use Concrete\Core\Attribute\Category\UserCategory;
use Concrete\Core\Attribute\MulticolumnTextExportableAttributeInterface;
use Concrete\Core\Attribute\SimpleTextExportableAttributeInterface;
use Concrete\Core\Config\Repository\Repository;
use Concrete\Core\Localization\Service\Date;
use Concrete\Core\User\Group\GroupRepository;
use Concrete\Core\User\UserInfo;
use Concrete\Core\User\UserInfoRepository;
use Doctrine\Common\Util\ClassUtils;
use Doctrine\ORM\EntityManagerInterface;
use League\Csv\Writer;
use Macareux\CsvUserImportExport\Traits\CsvTrait;

class Exporter
{
    use CsvTrait;

    /**
     * @var Writer
     */
    protected $writer;

    /**
     * @var array
     */
    protected $columns;

    /**
     * @var string
     */
    protected $format;

    /**
     * @var Date
     */
    protected $dateHelper;

    /**
     * @var UserInfoRepository
     */
    protected $userInfoRepository;

    /**
     * @var GroupRepository
     */
    protected $groupRepository;

    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;

    /**
     * @var UserCategory
     */
    protected $attributeCategory;

    /**
     * @var int
     */
    protected $unloadDoctrineEveryTick = 50;

    protected $ticksUntilUnload;

    /**
     * {@inheritdoc}
     */
    public function __construct(Writer $writer, array $columns, Repository $config, Date $dateHelper, UserInfoRepository $userInfoRepository, GroupRepository $groupRepository, EntityManagerInterface $entityManager, UserCategory $userCategory)
    {
        $this->writer = $writer;
        $this->columns = $columns;
        $this->format = $this->getFormatByName($config->get('concrete.export.csv.datetime_format', 'ATOM'));
        $this->dateHelper = $dateHelper;
        $this->userInfoRepository = $userInfoRepository;
        $this->groupRepository = $groupRepository;
        $this->entityManager = $entityManager;
        $this->attributeCategory = $userCategory;
        if ($config->get('concrete.export.csv.ticks_until_unload') !== null) {
            $this->setUnloadDoctrineEveryTick($config->get('concrete.export.csv.ticks_until_unload'));
        }
    }

    public function setUnloadDoctrineEveryTick(int $value): void
    {
        $this->unloadDoctrineEveryTick = max(0, $value);
        $this->ticksUntilUnload = $this->unloadDoctrineEveryTick ?: null;
    }

    public function tick(): void
    {
        if ($this->ticksUntilUnload !== null) {
            $this->ticksUntilUnload--;
            if ($this->ticksUntilUnload < 1) {
                $this->unloadDoctrineEntities();
                $this->ticksUntilUnload = $this->unloadDoctrineEveryTick;
            }
        }
    }

    public function insert()
    {
        $this->writer->insertOne(iterator_to_array($this->getHeaders()));
        $this->writer->insertAll($this->getRecords());
    }

    protected function getHeaders(): \Generator
    {
        foreach ($this->columns as $column) {
            yield $this->getHeaderNameByHandle($column);
        }
    }

    protected function getValues(UserInfo $userInfo): \Generator
    {
        foreach ($this->columns as $column) {
            yield $this->getValueByHandle($userInfo, $column);
        }
    }

    protected function getValueByHandle(UserInfo $userInfo, string $handle): string
    {
        $value = null;
        $user = $userInfo->getUserObject();

        switch ($handle) {
            case 'id':
                $value = $userInfo->getUserID();
                break;
            case 'username':
                $value = $userInfo->getUserName();
                break;
            case 'email':
                $value = $userInfo->getUserEmail();
                break;
            case 'date_created':
                $value = $userInfo->getUserDateAdded();
                break;
            case 'date_updated':
                $value = $userInfo->getUserDateLastUpdated();
                break;
            case 'last_ip':
                $value = $userInfo->getLastIPAddress();
                break;
            case 'last_login':
                $value = $userInfo->getLastLogin();
                break;
            case 'last_online':
                $value = $userInfo->getLastOnline();
                break;
            case 'total_login':
                $value = $userInfo->getNumLogins();
                break;
            case 'previous_login':
                $value = $userInfo->getPreviousLogin();
                break;
            case 'timezone':
                $value = $userInfo->getUserTimezone();
                break;
            case 'language':
                $value = $userInfo->getUserDefaultLanguage();
                break;
            case 'active':
                $value = $userInfo->isActive();
                break;
            case 'validated':
                $value = $userInfo->isValidated();
                break;
        }

        if (substr($handle, 0, 2) === 'g:') {
            $group = $this->groupRepository->getGroupById(substr($handle, 2));
            $value = $user->inGroup($group);
        }

        if (substr($handle, 0, 2) === 'a:') {
            $keyHandle = substr($handle, 2);
            $subHandle = '';
            if (strpos($keyHandle, '[') !== false) {
                $chunks = explode('[', rtrim($keyHandle, ']'));
                $keyHandle = $chunks[0];
                $subHandle = $chunks[1];
            }
            $key = $this->attributeCategory->getAttributeKeyByHandle($keyHandle);
            $attributeValue = $userInfo->getAttributeValueObject($key);
            $attributeController = $key->getController();
            $attributeController->setAttributeValue($attributeValue);
            if ($attributeController instanceof SimpleTextExportableAttributeInterface) {
                $value = $attributeController->getAttributeValueTextRepresentation();
            } elseif ($attributeController instanceof MulticolumnTextExportableAttributeInterface) {
                $subHeaders = $attributeController->getAttributeTextRepresentationHeaders();
                $subValues = $attributeController->getAttributeValueTextRepresentation();
                $subIndex = array_search($subHandle, $subHeaders);
                $value = $subValues[$subIndex];
            }
        }

        return $this->convertToString($value);
    }

    protected function convertToString($value): string
    {
        if ($value instanceof \DateTime) {
            $value = $this->dateHelper->formatCustom($this->format, $value, 'app');
        }

        if (is_bool($value)) {
            $value = $value ? '1' : '0';
        }

        return (string) $value;
    }

    protected function unloadDoctrineEntities(): void
    {
        $this->entityManager->clear();
        $categoryClass = ClassUtils::getClass($this->attributeCategory);
        if (!$this->entityManager->getMetadataFactory()->isTransient($categoryClass)) {
            // @todo merge function will be removed from Doctrine ORM
            $this->entityManager->merge($this->attributeCategory);
        }
    }

    protected function getRecords(): \Generator
    {
        foreach ($this->userInfoRepository->all() as $userInfo) {
            yield iterator_to_array($this->getValues($userInfo));
            $this->tick();
        }
    }

    protected function getFormatByName(string $formatName = 'ATOM')
    {
        $datetime_format_constant = sprintf('DATE_%s', $formatName);

        if (defined($datetime_format_constant)) {
            return constant($datetime_format_constant);
        }

        return DATE_ATOM;
    }
}
