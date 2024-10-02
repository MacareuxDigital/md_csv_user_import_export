<?php

namespace Concrete\Package\MdCsvUserImportExport;

use Concrete\Core\Package\Package;

class Controller extends Package
{
    /**
     * @var string package handle
     */
    protected $pkgHandle = 'md_csv_user_import_export';

    /**
     * @var string required concrete5 version
     */
    protected $appVersionRequired = '9.0.0';

    /**
     * @var string package version
     */
    protected $pkgVersion = '0.9.1';

    /**
     * {@inheritdoc}
     */
    protected $pkgAutoloaderRegistries = [
        'src' => '\Macareux\CsvUserImportExport',
    ];

    /**
     * Returns the translated package description.
     *
     * @return string
     */
    public function getPackageDescription()
    {
        return t('Import users from csv file, export users as csv file.');
    }

    /**
     * Returns the installed package name.
     *
     * @return string
     */
    public function getPackageName()
    {
        return t('Macareux CSV User Import & Export');
    }

    public function install()
    {
        $pkg = parent::install();

        $this->installContentFile('config/singlepages.xml');

        return $pkg;
    }
}
