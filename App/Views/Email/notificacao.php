<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $titulo ?? 'Notificação' ?></title>
    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 600px;
            margin: 20px auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #ffffff;
            padding: 40px 20px;
            text-align: center;
        }
        .header h1 {
            margin: 0 0 10px 0;
            font-size: 28px;
        }
        .content {
            padding: 30px 20px;
        }
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            border-left: 4px solid;
        }
        .alert-info {
            background-color: #d1ecf1;
            border-color: #0c5460;
            color: #0c5460;
        }
        .alert-success {
            background-color: #d4edda;
            border-color: #155724;
            color: #155724;
        }
        .alert-warning {
            background-color: #fff3cd;
            border-color: #856404;
            color: #856404;
        }
        .alert-danger {
            background-color: #f8d7da;
            border-color: #721c24;
            color: #721c24;
        }
        .details {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .details dt {
            font-weight: bold;
            color: #495057;
            margin-top: 10px;
        }
        .details dt:first-child {
            margin-top: 0;
        }
        .details dd {
            margin-left: 0;
            color: #212529;
        }
        .btn {
            display: inline-block;
            padding: 12px 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #ffffff !important;
            text-decoration: none;
            border-radius: 5px;
            margin: 20px 0;
            text-align: center;
        }
        .footer {
            background-color: #f8f9fa;
            padding: 20px;
            text-align: center;
            font-size: 12px;
            color: #6c757d;
            border-top: 1px solid #dee2e6;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><?= $titulo ?? 'Notificação' ?></h1>
            <?php if (isset($subtitulo)): ?>
                <p style="margin: 0; opacity: 0.9;"><?= $subtitulo ?></p>
            <?php endif; ?>
        </div>

        <div class="content">
            <?php if (isset($tipo_alerta) && isset($mensagem)): ?>
                <div class="alert alert-<?= $tipo_alerta ?>">
                    <?= $mensagem ?>
                </div>
            <?php endif; ?>

            <?php if (isset($texto)): ?>
                <p><?= $texto ?></p>
            <?php endif; ?>

            <?php if (isset($detalhes) && is_array($detalhes)): ?>
                <div class="details">
                    <dl>
                        <?php foreach ($detalhes as $label => $valor): ?>
                            <dt><?= $label ?></dt>
                            <dd><?= $valor ?></dd>
                        <?php endforeach; ?>
                    </dl>
                </div>
            <?php endif; ?>

            <?php if (isset($botao_texto) && isset($botao_link)): ?>
                <p style="text-align: center;">
                    <a href="<?= $botao_link ?>" class="btn"><?= $botao_texto ?></a>
                </p>
            <?php endif; ?>

            <?php if (isset($observacao)): ?>
                <p style="font-size: 14px; color: #6c757d; margin-top: 20px;">
                    <em><?= $observacao ?></em>
                </p>
            <?php endif; ?>
        </div>

        <div class="footer">
            <p><?= $empresa ?? 'Ecletech Sistemas' ?></p>
            <p>Este é um email automático, não responda.</p>
            <p><?= date('Y') ?> © Todos os direitos reservados.</p>
        </div>
    </div>
</body>
</html>
