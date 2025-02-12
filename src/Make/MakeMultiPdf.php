<?php declare(strict_types=1);
/**
 * @author Nicolas CARPi <nico-git@deltablot.email>
 * @copyright 2012 Nicolas CARPi
 * @see https://www.elabftw.net Official website
 * @license AGPL-3.0
 * @package elabftw
 */

namespace Elabftw\Make;

use Elabftw\Exceptions\IllegalActionException;
use Elabftw\Interfaces\MpdfProviderInterface;
use Elabftw\Models\AbstractEntity;
use Elabftw\Models\Notifications\PdfAppendmentFailed;
use Elabftw\Models\Notifications\PdfGenericError;

/**
 * Make a PDF from several experiments or db items
 */
class MakeMultiPdf extends AbstractMakePdf
{
    public function __construct(private MpdfProviderInterface $mpdfProvider, AbstractEntity $entity, private array $idArr)
    {
        parent::__construct($mpdfProvider, $entity);
    }

    /**
     * Get the name of the generated file
     */
    public function getFileName(): string
    {
        return 'multientries.elabftw.pdf';
    }

    /**
     * Loop over each id and add it to the PDF
     * This could be called the main function.
     */
    public function getFileContent(): string
    {
        $idCount = count($this->idArr);
        foreach ($this->idArr as $key => $id) {
            $this->addToPdf((int) $id);

            if ($key !== $idCount - 1) {
                $this->mpdf->AddPageByArray(array(
                    'sheet-size' => $this->Entity->Users->userData['pdf_format'],
                ));
            }
        }
        if ($this->errors) {
            $Notifications = new PdfGenericError();
            $Notifications->create($this->Entity->Users->userData['userid']);
        }

        return $this->mpdf->Output('', 'S');
    }

    /**
     * This is where the magic happens
     */
    private function addToPdf(int $id): void
    {
        $this->Entity->setId($id);
        try {
            $permissions = $this->Entity->getPermissions();
        } catch (IllegalActionException) {
            return;
        }
        if ($permissions['read']) {
            $currentEntity = new MakePdf($this->mpdfProvider, $this->Entity);
            $currentEntity->setNotifications(false);
            // write content
            $this->mpdf->WriteHTML($currentEntity->getContent());

            // attached files are appended based on user setting
            if ($this->Entity->Users->userData['append_pdfs']) {
                $currentEntity->appendPdfs($currentEntity->getAttachedPdfs(), $this->mpdf);
                if ($currentEntity->failedAppendPdfs) {
                    /** @psalm-suppress PossiblyNullArgument */
                    $currentEntity->errors[] = new PdfAppendmentFailed(
                        $currentEntity->Entity->id,
                        $currentEntity->Entity->page,
                        implode(', ', $currentEntity->failedAppendPdfs),
                    );
                }
            }
            array_push($this->errors, ...$currentEntity->errors);
        }
    }
}
