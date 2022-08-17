<?php

namespace Concrete\Package\MdCsvUserImportExport\Controller\SinglePage\Dashboard\Users;

use Concrete\Core\Command\Batch\Batch;
use Concrete\Core\Config\Repository\Liaison;
use Concrete\Core\File\File;
use Concrete\Core\Http\ResponseFactoryInterface;
use Concrete\Core\Package\PackageService;
use Concrete\Core\Page\Controller\DashboardPageController;
use Concrete\Core\Permission\Checker;
use League\Csv\Reader;
use Macareux\CsvUserImportExport\File\Command\DeleteFileCommand;
use Macareux\CsvUserImportExport\Traits\CsvTrait;
use Macareux\CsvUserImportExport\User\Command\ImportUserCommand;

class Import extends DashboardPageController
{
    use CsvTrait;

    public function view()
    {
        $this->set('html', $this->app->make('helper/concrete/file_manager'));
    }

    public function select_mapping()
    {
        $fID = $this->post('csv');
        if ($fID) {
            $f = File::getByID($fID);
            $fp = new Checker($f);
            if ($f && $fp->canRead()) {
                if ($f->getApprovedVersion()->getExtension() === 'csv') {
                    $reader = Reader::createFromStream($f->getApprovedVersion()->getFileResource()->readStream());
                    $reader->setHeaderOffset(0);
                    $this->set('fID', $fID);
                    $this->set('canDelete', $fp->canDeleteFile());
                    $this->set('header', $reader->getHeader());
                } else {
                    $this->error->add(t('Please select a valid CSV file.'));
                }
            } else {
                $this->error->add(t('Unable to read the file.'));
            }
        } else {
            $this->error->add(t('Please select a CSV file.'));
        }

        $this->set('selected_headers', $this->getPackageConfig()->get('csv.import_columns', []));
        $this->set('headers', $this->getImportableHeaders());
        $this->set('pageTitle', t('CSV Columns Mapping'));
        $this->render('/dashboard/users/import/mapping', 'md_csv_user_import_export');
    }

    public function do_import()
    {
        if (!$this->token->validate('import_user_csv')) {
            $this->error->add($this->token->getErrorMessage());
        }

        $permission = new Checker();
        if (!$permission->canAccessUserSearchExport()) {
            $this->error->add(t('You have no access to user export.'));
        }

        /**
         * @example ['handle' => 'CSV Column Header', 'handle[sub_handle]' => 'CSV Column Header']]
         */
        $columns = [];
        foreach ($this->getImportableHeaders(false) as $handle => $label) {
            $selectedColumnName = $this->post($handle);
            if ($selectedColumnName) {
                if (is_array($selectedColumnName)) {
                    foreach ($selectedColumnName as $subHandle => $subName) {
                        $combinedHandle = $handle . '[' . $subHandle . ']';
                        $columns[$combinedHandle] = $subName;
                    }
                } else {
                    $columns[$handle] = $selectedColumnName;
                }
            }
        }

        if (!is_array($columns) || count($columns) === 0) {
            $this->error->add(t('Please select import headers.'));
        } elseif (!array_key_exists('username', $columns) && !array_key_exists('email', $columns)) {
            $this->error->add(t('You need username or email to import users.'));
        }

        if (!$this->error->has()) {
            if ($this->post('save')) {
                $this->getPackageConfig()->save('csv.import_columns', $columns);
            } else {
                $this->getPackageConfig()->save('csv.import_columns', []);
            }

            $fID = $this->post('csv');
            $f = File::getByID($fID);
            $fp = new Checker($f);
            $deleteFile = null;
            if ($this->post('delete') && $fp->canDeleteFile()) {
                $deleteFile = $fID;
            }
            if ($fp->canViewFile()) {
                $reader = Reader::createFromStream($f->getApprovedVersion()->getFileResource()->readStream());
                $reader->setHeaderOffset(0);
                $batch = Batch::create(t('Import Users'), function () use ($reader, $columns, $deleteFile) {
                    /**
                     * @var array $results
                     *
                     * @example [['CSV Header' => 'value']]
                     */
                    $results = $reader->getRecords();
                    foreach ($results as $index => $result) {
                        /**
                         * @example [['handle' => 'value'], ['handle[sub_handle]' => 'value']]
                         */
                        $row = [];
                        foreach ($columns as $handle => $headerName) {
                            $row[$handle] = $result[$headerName];
                        }
                        $command = new ImportUserCommand();
                        $command->setLine($index + 1);
                        $command->setRow($row);
                        yield $command;
                    }

                    if ($deleteFile) {
                        yield new DeleteFileCommand($deleteFile);
                    }
                });

                return $this->dispatchBatch($batch);
            }
            $this->error->add(t('File permission denied.'));
        }

        return $this->app->make(ResponseFactoryInterface::class)->error($this->error);
    }

    protected function getPackageConfig(): ?Liaison
    {
        /** @var PackageService $packageService */
        $packageService = $this->app->make(PackageService::class);
        $package = $packageService->getClass('md_csv_uer_import_export');

        return $package->getFileConfig();
    }
}
