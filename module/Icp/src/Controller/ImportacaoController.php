<?php

declare(strict_types=1);

namespace Icp\Controller;

use Doctrine\DBAL\Exception as DbalException;
use Icp\Exception\EstruturaInvalidaException;
use Icp\Exception\ImportacaoEmAndamentoException;
use Icp\Form\ImportacaoForm;
use Icp\Service\ImportadorEstrutura;
use Laminas\Http\Request;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\Stdlib\ArrayUtils;
use Laminas\View\Model\ViewModel;

use function file_get_contents;
use function set_time_limit;

/**
 * Upload do structure.json do ITI para popular AC, AC N2 e AR.
 */
final class ImportacaoController extends AbstractActionController
{
    public function __construct(private ImportadorEstrutura $importador)
    {
    }

    public function indexAction(): ViewModel
    {
        $form      = new ImportacaoForm();
        $resultado = null;
        $erro      = null;

        /** @var Request $request */
        $request = $this->getRequest();

        if ($request->isPost()) {
            $form->setData(ArrayUtils::merge($request->getPost()->toArray(), $request->getFiles()->toArray()));

            if ($form->isValid()) {
                /** @var array{arquivo: array{tmp_name: string}} $dados */
                $dados    = $form->getData();
                $conteudo = file_get_contents($dados['arquivo']['tmp_name']);

                try {
                    // O arquivo oficial gera ~8,5 mil operações; evita estourar o limite padrão.
                    set_time_limit(120);
                    $resultado = $this->importador->importarJson($conteudo === false ? '' : $conteudo);
                } catch (EstruturaInvalidaException | ImportacaoEmAndamentoException $e) {
                    $erro = $e->getMessage();
                } catch (DbalException) {
                    // Rede de segurança: a transação já foi desfeita e nada do arquivo foi gravado.
                    $erro = 'O banco de dados recusou os dados do arquivo. Nada foi gravado.';
                }
            }
        }

        return new ViewModel([
            'form'      => $form,
            'resultado' => $resultado,
            'erro'      => $erro,
        ]);
    }
}
