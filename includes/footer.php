<?php declare(strict_types=1);
// Assinatura do autor no rodapé. Para virar link, coloque o endereço do portfólio em $autorLink.
$autorMarca = 'Or7';
$autorNome  = 'Lorenzo Orsetti';
$autorLink  = '';   // ex.: 'https://seu-portfolio.com'
?>
<footer class="rodape">
  <div class="rodape__linha" aria-hidden="true"></div>
  <div class="rodape__grade">
    <div class="rodape__manifesto">
      <p class="kicker">Plataforma informativa e apartidária</p>
      <p class="rodape__frase">Organizamos o que está escrito.<br><span class="outline">Quem decide é você.</span></p>
    </div>
    <div class="rodape__col">
      <p class="mono rodape__tit">Navegar</p>
      <ul>
        <li><a href="<?= h(link_pagina('temas')) ?>">Temas</a></li>
        <li><a href="<?= h(link_pagina('candidatos')) ?>">Candidaturas</a></li>
        <li><a href="<?= h(link_pagina('comparar')) ?>">Comparar</a></li>
        <li><a href="<?= h(link_pagina('busca')) ?>">Busca</a></li>
      </ul>
    </div>
    <div class="rodape__col">
      <p class="mono rodape__tit">Transparência</p>
      <ul>
        <li><a href="<?= h(link_pagina('metodologia')) ?>">Como analisamos as propostas</a></li>
        <li><a href="<?= h(link_pagina('metodologia')) ?>#nao-fazemos">O que não fazemos</a></li>
        <li><a href="<?= h(link_pagina('atualizacoes')) ?>">Últimas atualizações</a></li>
        <li><a href="<?= h(link_pagina('correcoes')) ?>">Encontrou um erro?</a></li>
        <li><a href="<?= h(link_pagina('privacidade')) ?>">Privacidade</a></li>
      </ul>
    </div>
    <div class="rodape__col rodape__col--nota">
      <p class="mono rodape__tit">Aviso</p>
      <p>O <?= h(SITE_NAME) ?> não recomenda candidaturas, não atribui notas e não cria rankings. Os resumos são elaborados pela plataforma e sempre acompanham o texto original do documento.</p>
      <?php if (demo_ativo()): ?><p class="rodape__demo mono">Esta versão usa candidaturas, partidos e propostas fictícias para demonstração.</p><?php endif; ?>
    </div>
  </div>
  <div class="rodape__marca" aria-hidden="true"><?= h(mb_strtoupper(SITE_NAME)) ?></div>
  <p class="rodape__base mono"><span>© <?= date('Y') ?> <?= h(SITE_NAME) ?> · <?= h(SITE_TAGLINE) ?></span><span>Eleições · Brasil · 2026</span></p>
  <<?= $autorLink ? 'a href="' . h($autorLink) . '" target="_blank" rel="noopener author"' : 'div' ?> class="assinatura" aria-label="Idealizado e desenvolvido por <?= h($autorNome) ?> (<?= h($autorMarca) ?>)">
    <span class="assinatura__marca" aria-hidden="true">O<span>r</span><em>7</em></span>
    <span class="assinatura__txt">
      <span class="mono">Idealizado e desenvolvido por</span>
      <strong><?= h($autorNome) ?></strong>
    </span>
  </<?= $autorLink ? 'a' : 'div' ?>>
</footer>

<!-- Busca rápida -->
<dialog class="busca-overlay" id="busca-overlay" aria-label="Buscar propostas">
  <form class="busca-overlay__form" action="<?= h(link_pagina('busca')) ?>" method="get" role="search">
    <p class="mono busca-overlay__cab"><span>Busca</span><button type="button" class="fechar" data-fechar aria-label="Fechar busca">Esc ✕</button></p>
    <label class="busca-overlay__label" for="busca-overlay-q">O que você quer entender sobre o Brasil?</label>
    <div class="busca-overlay__campo">
      <input id="busca-overlay-q" name="q" type="search" autocomplete="off" maxlength="120" placeholder="Ex.: fila do SUS, creches, imposto de renda" data-sugestoes="busca-overlay-lista">
      <button type="submit" class="busca-overlay__ir" aria-label="Pesquisar">→</button>
    </div>
    <div class="sugestoes" id="busca-overlay-lista" role="listbox" aria-label="Sugestões"></div>
  </form>
</dialog>

<!-- Painel de fonte original -->
<dialog class="modal-fonte" id="modal-fonte" aria-labelledby="modal-fonte-titulo">
  <div class="modal-fonte__corpo">
    <header class="modal-fonte__topo">
      <p class="mono" id="modal-fonte-titulo">Fonte da proposta</p>
      <button type="button" class="fechar" data-fechar aria-label="Fechar">Fechar ✕</button>
    </header>
    <div class="modal-fonte__grade">
      <section class="modal-fonte__resumo">
        <p class="rotulo mono"><span class="rotulo__marca rotulo__marca--plataforma"></span>Resumo da proposta</p>
        <p class="modal-fonte__nota">Texto elaborado pela plataforma. Não é uma declaração do candidato.</p>
        <p class="modal-fonte__resumo-txt" data-m="resumo"></p>
        <p class="mono modal-fonte__cand" data-m="cand"></p>
      </section>
      <section class="modal-fonte__original">
        <div class="papel">
          <p class="rotulo mono"><span class="rotulo__marca rotulo__marca--doc"></span>Texto original</p>
          <p class="modal-fonte__nota">Transcrição literal do documento, sem alterações.</p>
          <blockquote class="papel__trecho" data-m="trecho"></blockquote>
          <dl class="papel__ref mono">
            <div><dt>Documento</dt><dd data-m="titulo"></dd></div>
            <div><dt>Tipo</dt><dd data-m="tipo"></dd></div>
            <div><dt>Localização</dt><dd data-m="pagina"></dd></div>
            <div><dt>Seção</dt><dd data-m="local"></dd></div>
          </dl>
          <a class="btn-line btn-line--papel" data-m="doc" href="#" target="_blank" rel="noopener">Abrir documento nesta página <span aria-hidden="true">↗</span></a>
        </div>
      </section>
    </div>
  </div>
</dialog>
<!-- Compartilhar -->
<dialog class="compartilhar" id="modal-compartilhar" aria-labelledby="compartilhar-tit">
  <div class="compartilhar__corpo">
    <header class="compartilhar__topo">
      <p class="mono" id="compartilhar-tit">Compartilhar</p>
      <button type="button" class="fechar" data-fechar aria-label="Fechar">Fechar ✕</button>
    </header>
    <p class="compartilhar__titulo" data-c="titulo"></p>
    <label class="mono compartilhar__rot" for="compartilhar-url">Link</label>
    <div class="compartilhar__campo">
      <input id="compartilhar-url" type="text" readonly data-c="url">
      <button type="button" class="btn-solid btn-solid--pequeno" data-c-copiar>Copiar</button>
    </div>
    <div class="compartilhar__redes">
      <a class="rede rede--whatsapp" data-c="whatsapp" href="#" target="_blank" rel="noopener">WhatsApp</a>
      <a class="rede" data-c="telegram" href="#" target="_blank" rel="noopener">Telegram</a>
      <a class="rede" data-c="x" href="#" target="_blank" rel="noopener">X</a>
      <a class="rede" data-c="email" href="#">E-mail</a>
      <button type="button" class="rede" data-c-nativo hidden>Mais opções</button>
    </div>
    <p class="compartilhar__status mono" data-c-status role="status" aria-live="polite"></p>
  </div>
</dialog>
</body>
</html>
