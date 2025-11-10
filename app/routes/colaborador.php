<?php

use App\Controllers\ControllerColaborador;
use App\Middleware\MiddlewareAcl;

/**
 * Rotas de colaboradores
 * Requerem autenticação + permissões específicas (ACL)
 */

return function($roteador) {
    $roteador->grupo([
        'prefixo' => 'colaboradores',
        'middleware' => ['auth', 'admin']
    ], function($roteador) {
        // Listar colaboradores - requer permissão de visualização
        $roteador->get('/', [ControllerColaborador::class, 'listar'])
            ->middleware(MiddlewareAcl::requer('colaboradores.visualizar'));

        // Buscar colaborador por ID - requer permissão de visualização
        $roteador->get('/{id}', [ControllerColaborador::class, 'buscar'])
            ->middleware(MiddlewareAcl::requer('colaboradores.visualizar'));

        // Criar colaborador - requer permissão de criação
        $roteador->post('/', [ControllerColaborador::class, 'criar'])
            ->middleware(MiddlewareAcl::requer('colaboradores.criar'));

        // Atualizar colaborador - requer permissão de edição
        $roteador->put('/{id}', [ControllerColaborador::class, 'atualizar'])
            ->middleware(MiddlewareAcl::requer('colaboradores.editar'));

        // Deletar colaborador - requer permissão de exclusão
        $roteador->delete('/{id}', [ControllerColaborador::class, 'deletar'])
            ->middleware(MiddlewareAcl::requer('colaboradores.deletar'));
    });
};
