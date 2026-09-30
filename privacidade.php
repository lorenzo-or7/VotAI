<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/componentes.php';

$atualizada = '29/09/2026';

$pageId        = 'como-funciona';
$pageTitle     = 'Privacidade';
$pageDesc      = 'Quais dados o ' . SITE_NAME . ' usa (quase nenhum), por quê e como exercer seus direitos pela LGPD.';
$pageCanonical = link_pagina('privacidade');

require __DIR__ . '/includes/header.php';
?>
<main class="page" id="conteudo">
  <header class="topo-pagina wrap">
    <p class="kicker" data-reveal>Transparência · Privacidade</p>
    <h1 class="display display--xl topo-pagina__titulo">
      <span class="mascara"><span>Seus dados</span></span>
      <span class="mascara"><span class="outline">ficam com você.</span></span>
    </h1>
    <p class="lead" data-reveal>O <?= h(SITE_NAME) ?> não pede cadastro, não usa anúncios, não tem ferramentas de rastreamento e não vende nem compartilha dados. Esta página explica, em linguagem simples, o pouco que é usado.</p>
  </header>

  <section class="texto-legal wrap">
    <p class="texto-legal__data mono">Última atualização: <?= h($atualizada) ?></p>

    <h2>Resumo</h2>
    <ul>
      <li><strong>Sem cadastro, sem login</strong> para quem visita o site.</li>
      <li><strong>Sem cookies de rastreamento</strong>, sem Google Analytics, sem pixel de redes sociais, sem anúncios.</li>
      <li><strong>Sem serviços de terceiros carregados nas páginas</strong>: fontes, imagens e códigos ficam no próprio servidor.</li>
      <li>O único dado pessoal que pode chegar até nós é o que você escolher enviar no <a href="<?= h(link_pagina('correcoes')) ?>">canal de correções</a>.</li>
    </ul>

    <h2>1. Quem é o responsável</h2>
    <p>O <?= h(SITE_NAME) ?> é um projeto independente e apartidário, idealizado e mantido por Lorenzo Orsetti, que é o controlador dos dados tratados aqui, nos termos da Lei Geral de Proteção de Dados (Lei nº 13.709/2018). Contato para assuntos de privacidade: <a href="<?= h(link_pagina('correcoes')) ?>">canal de correções</a>, opção “Pedido sobre dados pessoais (LGPD)”.</p>

    <h2>2. O que fica guardado no seu navegador</h2>
    <p>Apenas a sua preferência de <strong>modo claro ou escuro</strong>, gravada no próprio navegador (armazenamento local). Ela não identifica você, não é enviada ao servidor e pode ser apagada limpando os dados do site no navegador.</p>

    <h2>3. Registros técnicos do servidor</h2>
    <p>Como qualquer site, a empresa de hospedagem registra automaticamente dados técnicos de cada acesso (endereço IP, data e hora, página pedida, tipo de navegador) para segurança e funcionamento. Esses registros não são usados para identificar visitantes nem cruzados com outras informações, e são apagados pela hospedagem conforme a política dela.</p>

    <h2>4. Canal de correções</h2>
    <p>Se você enviar um relato de erro, guardamos:</p>
    <ul>
      <li>o conteúdo do relato (tipo de problema, número da proposta, página e descrição);</li>
      <li>seu e-mail, <strong>somente se você informar</strong> — ele é opcional e usado apenas para responder àquele relato;</li>
      <li>um código cifrado derivado do seu IP (não o IP em si), usado só para impedir envios em massa e <strong>apagado em 30 dias</strong>.</li>
    </ul>
    <p>Finalidade: conferir e corrigir informações do site. Base legal: legítimo interesse em manter a informação correta (art. 7º, IX) e, quanto ao e-mail, o seu consentimento ao informá-lo (art. 7º, I). Os relatos são guardados até 12 meses depois do fim do período eleitoral e depois apagados.</p>

    <h2>5. Botões de compartilhar</h2>
    <p>Os botões de WhatsApp, Telegram, X e e-mail só abrem o serviço escolhido <strong>quando você clica</strong>. Antes disso, nenhum dado é enviado a eles. Ao usar um desses serviços, vale a política de privacidade de cada um.</p>

    <h2>6. Dados das candidaturas</h2>
    <p>Nomes, números, partidos, fotos e planos de governo das candidaturas são informações públicas, divulgadas pela Justiça Eleitoral no Portal de Dados Abertos do TSE, e são usadas aqui apenas para informar o eleitor, sem juízo de valor. Correções sobre esses dados podem ser pedidas pelo canal de correções.</p>

    <h2>7. Seus direitos</h2>
    <p>Pela LGPD você pode pedir, a qualquer momento: confirmação de que tratamos dados seus, acesso, correção, anonimização ou exclusão, e informações sobre o uso. Para isso, use o <a href="<?= h(link_pagina('correcoes')) ?>">canal de correções</a> com a opção “Pedido sobre dados pessoais (LGPD)”, informando o e-mail usado no relato, se houver. Você também pode reclamar à Autoridade Nacional de Proteção de Dados (ANPD).</p>

    <h2>8. Segurança</h2>
    <p>O site usa conexão cifrada (HTTPS), consultas protegidas ao banco de dados e acesso ao painel restrito à equipe de revisão, com senha forte e limite de tentativas. Nenhum sistema é totalmente imune, mas guardamos o mínimo de dados justamente para reduzir riscos.</p>

    <h2>9. Mudanças nesta página</h2>
    <p>Se esta política mudar, a data no topo desta página será atualizada.</p>
  </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
