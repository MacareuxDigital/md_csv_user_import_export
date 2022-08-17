<?php

namespace Macareux\CsvUserImportExport\User\Command;

use Concrete\Core\Attribute\MulticolumnTextExportableAttributeInterface;
use Concrete\Core\Attribute\SimpleTextExportableAttributeInterface;
use Concrete\Core\Error\ErrorList\ErrorList;
use Concrete\Core\Logging\LoggerFactory;
use Concrete\Core\Support\Facade\Application;
use Concrete\Core\User\RegistrationService;
use Concrete\Core\User\UserInfoRepository;
use Concrete\Core\Utility\Service\Identifier;
use Concrete\Core\Validator\ValidatorForSubjectInterface;
use Doctrine\ORM\EntityManagerInterface;
use Macareux\CsvUserImportExport\Traits\CsvTrait;

class ImportUserCommandHandler
{
    use CsvTrait;

    public function __invoke(ImportUserCommand $command)
    {
        $app = Application::getFacadeApplication();
        /** @var UserInfoRepository $userInfoRepository */
        $userInfoRepository = $app->make(UserInfoRepository::class);
        /** @var Identifier $identifier */
        $identifier = $app->make('helper/validation/identifier');
        /** @var ValidatorForSubjectInterface $nameValidator */
        $nameValidator = $app->make('validator/user/name');
        /** @var ValidatorForSubjectInterface $passwordValidator */
        $passwordValidator = $app->make('validator/password');
        /** @var ValidatorForSubjectInterface $emailValidator */
        $emailValidator = $app->make('validator/user/email');
        /** @var LoggerFactory $loggerFactory */
        $loggerFactory = $app->make(LoggerFactory::class);
        $logger = $loggerFactory->createLogger('CSV User Import');

        /**
         * [['handle' => 'value'],['handle' => 'value']].
         */
        $row = $command->getRow();
        $username = $this->getValue($row, 'username');
        $email = $this->getValue($row, 'email');
        $timezone = $this->getValue($row, 'timezone');
        $language = $this->getValue($row, 'language');
        $active = $this->getValue($row, 'active');
        $validated = $this->getValue($row, 'validated');
        $password = $this->getValue($row, 'password');

        $line = $command->getLine();

        $data = [];
        if ($username) {
            $userInfo = $userInfoRepository->getByName($username);
        } elseif ($email) {
            $userInfo = $userInfoRepository->getByEmail($email);
        }

        /** @var ErrorList $error */
        $error = $app->make(ErrorList::class);
        if (isset($userInfo)) {
            if ($email && $email !== $userInfo->getUserEmail()) {
                $emailValidator->isValid($email, $error);
                $data['uEmail'] = $email;
            }
            if ($validated !== null) {
                $validated = (int) $validated;
                if (($validated && $userInfo->isValidated() === false) || (!$validated && $userInfo->isValidated() === true)) {
                    $data['uIsValidated'] = $validated;
                }
            }
            if ($password) {
                $passwordValidator->isValid($password, $error);
                $data['uPassword'] = $password;
            }
            if ($language) {
                $data['uDefaultLanguage'] = $language;
            }
            if ($error->has()) {
                $logger->info('Failed to update:' . $error->toText() . '(row ' . $line . ')');
            } else {
                $userInfo->update($data);
            }
        } else {
            if ($username) {
                $nameValidator->isValid($username, $error);
                $data['uName'] = $username;
            }
            if ($email) {
                $emailValidator->isValid($email, $error);
                $data['uEmail'] = $email;
            }
            if ($validated !== null) {
                $data['uIsValidated'] = (int) $validated;
            }
            if ($password) {
                $passwordValidator->isValid($password, $error);
                $data['uPassword'] = $password;
            } else {
                $data['uPassword'] = $identifier->getString();
            }
            if ($language) {
                $data['uDefaultLanguage'] = $language;
            }
            if (!$error->has()) {
                /** @var RegistrationService $registrationService */
                $registrationService = $app->make('user/registration');
                $userInfo = $registrationService->create($data);
            } else {
                $logger->info('Failed to add:' . $error->toText() . '(row ' . $line . ')');
            }
        }

        if (isset($userInfo)) {
            /** @var EntityManagerInterface $entityManager */
            $entityManager = $app->make(EntityManagerInterface::class);
            $entity = $userInfo->getEntityObject();
            $user = $userInfo->getUserObject();
            $isEntityUpdated = false;

            if ($timezone) {
                $entity->setUserTimezone($timezone);
                $isEntityUpdated = true;
            }
            if ($active !== null) {
                $active = (bool) $active;
                if ($active && $userInfo->isActive() === false) {
                    $userInfo->activate();
                }
                if (!$active && $userInfo->isActive() === true) {
                    $userInfo->deactivate();
                }
            }

            foreach ($this->getGroups() as $groupHandle => $group) {
                $selected = $this->getValue($row, $groupHandle);
                if ($selected !== null) {
                    $selected = (bool) $selected;
                    if ($selected) {
                        $user->enterGroup($group);
                    } else {
                        $user->exitGroup($group);
                    }
                }
            }

            /** @var ErrorList $attributesWarnings */
            $attributesWarnings = $app->make(ErrorList::class);
            foreach ($this->getAttributeKeys() as $keyHandle => $attributeKey) {
                $attributeValueEntity = null;
                $attributeController = $attributeKey->getController();
                $initialValue = $userInfo->getAttributeValueObject($attributeKey, false);
                if ($attributeController instanceof SimpleTextExportableAttributeInterface) {
                    $csvAttributeValue = $this->getValue($row, $keyHandle);
                    if ($csvAttributeValue !== null) {
                        $attributeValueEntity = $attributeController->updateAttributeValueFromTextRepresentation($csvAttributeValue, $attributesWarnings);
                    }
                } elseif ($attributeController instanceof MulticolumnTextExportableAttributeInterface) {
                    $subHeaders = $attributeController->getAttributeTextRepresentationHeaders();
                    $csvAttributeValues = $this->getValue($row, $keyHandle, $subHeaders);
                    if ($csvAttributeValues !== null) {
                        $attributeValueEntity = $attributeController->updateAttributeValueFromTextRepresentation($csvAttributeValues, $attributesWarnings);
                    }
                }
                if ($attributeValueEntity !== null) {
                    if ($attributeValueEntity === $initialValue) {
                        $entityManager->flush();
                    } else {
                        $userInfo->setAttribute($attributeKey, $attributeValueEntity);
                    }
                }
            }
            if ($attributesWarnings->has()) {
                $logger->info($attributesWarnings->toText());
            }

            if ($isEntityUpdated) {
                $entityManager->persist($entity);
                $entityManager->flush();
            }
        }
    }

    /**
     * @param array $row
     *
     *     @example [['handle' => 'value'], ['handle[sub_handle]' => 'value']]
     *
     * @param string $key
     * @param array $subHeaders
     *
     * @return string|array|null Return null if CSV value is empty
     */
    protected function getValue(array $row, string $key, array $subHeaders = [])
    {
        $value = null;
        if (isset($row[$key]) && $row[$key] !== '') {
            $value = $row[$key];
        }
        if ($value === null && $subHeaders) {
            $value = [];
            $valueExists = false;
            foreach ($subHeaders as $subHeader) {
                $subKey = $key . '[' . $subHeader . ']';
                if (isset($row[$subKey])) {
                    $subValue = $row[$subKey];
                    if ($subValue) {
                        $valueExists = true;
                    }
                    $value[] = $subValue;
                }
            }
            // If all sub columns are empty, we should keep original attribute value
            if (!$valueExists) {
                $value = null;
            }
        }

        return $value;
    }
}
