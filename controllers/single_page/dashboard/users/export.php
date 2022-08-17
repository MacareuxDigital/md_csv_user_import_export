<?php

namespace Concrete\Package\MdCsvUserImportExport\Controller\SinglePage\Dashboard\Users;

use Concrete\Core\Config\Repository\Liaison;
use Concrete\Core\Csv\WriterFactory;
use Concrete\Core\Package\PackageService;
use Concrete\Core\Page\Controller\DashboardPageController;
use Macareux\CsvUserImportExport\Traits\CsvTrait;
use Macareux\CsvUserImportExport\User\Exporter;
use Symfony\Component\HttpFoundation\StreamedResponse;

class Export extends DashboardPageController
{
    use CsvTrait;

    public function view()
    {
        $this->set('selected_headers', $this->getPackageConfig()->get('csv.export_columns', []));
        $this->set('headers', $this->getExportableHeaders());
    }

    public function do_export()
    {
        if (!$this->token->validate('export_user_csv')) {
            $this->error->add($this->token->getErrorMessage());
        }

        $columns = $this->post('headers');
        if (!is_array($columns) || count($columns) === 0) {
            $this->error->add(t('Please select export headers.'));
        }

        if (!$this->error->has()) {
            $app = $this->app;
            if ($this->post('save')) {
                $this->getPackageConfig()->save('csv.export_columns', $columns);
            } else {
                $this->getPackageConfig()->save('csv.export_columns', []);
            }

            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename=concrete_users_' . date('YmdHis') . '.csv',
            ];
            $config = $app->make('config');
            $bom = $config->get('concrete.export.csv.include_bom') ? $config->get('concrete.charset_bom') : '';

            return new StreamedResponse(function () use ($columns, $app, $bom, $headers) {
                /** @var Exporter $writer */
                $writer = $app->make(Exporter::class, [
                    'writer' => $app->make(WriterFactory::class)->createFromPath('php://output', 'w'),
                    'columns' => $columns,
                ]);
                echo $bom;
                $writer->insert();
            }, 200, $headers);
        }

        $this->view();
    }

    protected function getPackageConfig(): ?Liaison
    {
        /** @var PackageService $packageService */
        $packageService = $this->app->make(PackageService::class);
        $package = $packageService->getClass('md_csv_uer_import_export');

        return $package->getFileConfig();
    }
}
