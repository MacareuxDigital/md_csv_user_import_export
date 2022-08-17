<?php

namespace Macareux\CsvUserImportExport\User\Command;

use Concrete\Core\Foundation\Command\Command;

class ImportUserCommand extends Command
{
    protected $line = 0;

    protected $row = [];

    /**
     * @return int
     */
    public function getLine(): int
    {
        return $this->line;
    }

    /**
     * @param int $line
     */
    public function setLine(int $line): void
    {
        $this->line = $line;
    }

    /**
     * @return array
     */
    public function getRow(): array
    {
        return $this->row;
    }

    /**
     * @param array $row
     */
    public function setRow(array $row): void
    {
        $this->row = $row;
    }
}
