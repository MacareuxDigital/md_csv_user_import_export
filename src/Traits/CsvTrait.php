<?php

namespace Macareux\CsvUserImportExport\Traits;

use Concrete\Core\Attribute\Category\UserCategory;
use Concrete\Core\Attribute\MulticolumnTextExportableAttributeInterface;
use Concrete\Core\Attribute\SimpleTextExportableAttributeInterface;
use Concrete\Core\Support\Facade\Application;
use Concrete\Core\User\Group\Group;
use Concrete\Core\User\Group\GroupList;

trait CsvTrait
{
    protected function getExportableHeaders(): array
    {
        $staticHeaders = [
            'id' => t('ID'),
            'username' => t('Username'),
            'email' => t('Email'),
            'date_created' => t('Date Created'),
            'date_updated' => t('Date Last Updated'),
            'last_ip' => t('Last IP Address'),
            'last_login' => t('Last Login'),
            'last_online' => t('Last Online'),
            'total_login' => t('Total Login'),
            'previous_login' => t('Previous Login'),
            'timezone' => t('Timezone'),
            'language' => t('Language'),
            'active' => t('Active'),
            'validated' => t('Validated'),
        ];

        $groupHeaders = $this->getGroupHeaders();
        $attributeHeaders = $this->getAttributeHeaders();

        return $staticHeaders + $groupHeaders + $attributeHeaders;
    }

    protected function getImportableHeaders(bool $includeSubHeaders = true): array
    {
        $staticHeaders = [
            'username' => t('Username'),
            'email' => t('Email'),
            'timezone' => t('Timezone'),
            'language' => t('Language'),
            'active' => t('Active'),
            'validated' => t('Validated'),
            'password' => t('Password'),
        ];

        $groupHeaders = $this->getGroupHeaders();
        $attributeHeaders = $this->getAttributeHeaders($includeSubHeaders);

        return $staticHeaders + $groupHeaders + $attributeHeaders;
    }

    protected function getGroupHeaders(): array
    {
        $groupHeaders = [];
        $groupList = new GroupList();
        /** @var Group $group */
        foreach ($groupList->getResults() as $group) {
            $groupHandle = 'g:' . $group->getGroupID();
            $groupHeaders[$groupHandle] = $group->getGroupDisplayName(false);
        }

        return $groupHeaders;
    }

    protected function getGroups(): \Generator
    {
        $groupList = new GroupList();
        /** @var Group $group */
        foreach ($groupList->getResults() as $group) {
            $groupHandle = 'g:' . $group->getGroupID();
            yield $groupHandle => $group;
        }
    }

    protected function getAttributeHeaders(bool $includeSubHeaders = true): array
    {
        $attributeHeaders = [];
        $app = Application::getFacadeApplication();
        /** @var UserCategory $userCategory */
        $userCategory = $app->make(UserCategory::class);
        foreach ($userCategory->getList() as $attributeKey) {
            $keyHandle = 'a:' . $attributeKey->getAttributeKeyHandle();
            $attributeController = $attributeKey->getController();
            if ($attributeController instanceof SimpleTextExportableAttributeInterface) {
                $attributeHeaders[$keyHandle] = $attributeKey->getAttributeKeyDisplayName();
            } elseif ($attributeController instanceof MulticolumnTextExportableAttributeInterface) {
                if ($includeSubHeaders) {
                    foreach ($attributeController->getAttributeTextRepresentationHeaders() as $subHeader) {
                        $subHandle = $keyHandle . '[' . $subHeader . ']';
                        $attributeHeaders[$subHandle] = $attributeKey->getAttributeKeyDisplayName() . '(' . $subHeader . ')';
                    }
                } else {
                    $attributeHeaders[$keyHandle] = $attributeKey->getAttributeKeyDisplayName();
                }
            }
        }

        return $attributeHeaders;
    }

    protected function getAttributeKeys(): \Generator
    {
        $app = Application::getFacadeApplication();
        /** @var UserCategory $userCategory */
        $userCategory = $app->make(UserCategory::class);
        foreach ($userCategory->getList() as $attributeKey) {
            $keyHandle = 'a:' . $attributeKey->getAttributeKeyHandle();
            $attributeController = $attributeKey->getController();
            if ($attributeController instanceof SimpleTextExportableAttributeInterface || $attributeController instanceof MulticolumnTextExportableAttributeInterface) {
                yield $keyHandle => $attributeKey;
            }
        }
    }

    protected function getHeaderNameByHandle(string $handle): string
    {
        $name = '';

        $exportableHeaders = $this->getExportableHeaders();
        if (isset($exportableHeaders[$handle])) {
            $name = $exportableHeaders[$handle];
        }

        if ($name === '') {
            $name = $handle;
        }

        return $name;
    }
}
