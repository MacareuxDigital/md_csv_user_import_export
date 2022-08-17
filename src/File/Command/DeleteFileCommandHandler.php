<?php

namespace Macareux\CsvUserImportExport\File\Command;

use Concrete\Core\File\File;

class DeleteFileCommandHandler
{
    public function __invoke(DeleteFileCommand $command)
    {
        $fID = $command->getFileID();
        $f = File::getByID($fID);
        $f->delete();
    }
}
