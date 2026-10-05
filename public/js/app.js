/*
 * Comportamentos de interface do CRUD ICP-Brasil (sem dependências além do Bootstrap).
 */
(function () {
    'use strict';

    // Modal do QR Code: carrega a imagem do botão que abriu o modal.
    var modal = document.getElementById('modalQrCode');
    if (modal) {
        modal.addEventListener('show.bs.modal', function (evento) {
            var botao = evento.relatedTarget;
            if (!botao) {
                return;
            }

            var imagem = botao.getAttribute('data-qrcode-imagem');
            var link = botao.getAttribute('data-qrcode-link');

            modal.querySelector('.modal-title').textContent = 'QR Code · ' + botao.getAttribute('data-qrcode-titulo');
            modal.querySelector('[data-qrcode-alvo="imagem"]').src = imagem;

            var ancora = modal.querySelector('[data-qrcode-alvo="link"]');
            ancora.href = link;
            ancora.textContent = link;

            modal.querySelector('[data-qrcode-alvo="download"]').href = imagem + '?download=1';
        });

        modal.addEventListener('hidden.bs.modal', function () {
            modal.querySelector('[data-qrcode-alvo="imagem"]').src = '';
        });
    }

    // Confirmação antes de enviar formulários de exclusão.
    document.addEventListener('submit', function (evento) {
        var botao = evento.submitter || evento.target.querySelector('[data-confirmar]');
        var mensagem = botao && botao.getAttribute('data-confirmar');
        if (mensagem && !window.confirm(mensagem)) {
            evento.preventDefault();
        }
    });

    // Botão com estado "carregando" (ex.: importação, que pode levar alguns segundos).
    document.querySelectorAll('[data-carregando]').forEach(function (botao) {
        botao.form.addEventListener('submit', function () {
            botao.disabled = true;
            botao.textContent = botao.getAttribute('data-carregando');
        });
    });

    // Filtro de opções de um <select multiple> (AC N2 no formulário de AR).
    document.querySelectorAll('[data-filtrar-select]').forEach(function (campo) {
        var select = document.getElementById(campo.getAttribute('data-filtrar-select'));
        if (!select) {
            return;
        }
        campo.addEventListener('input', function () {
            var termo = normalizar(campo.value);
            select.querySelectorAll('optgroup').forEach(function (grupo) {
                var grupoCombina = normalizar(grupo.label).indexOf(termo) !== -1;
                var algumVisivel = false;
                grupo.querySelectorAll('option').forEach(function (opcao) {
                    var visivel = grupoCombina || normalizar(opcao.textContent).indexOf(termo) !== -1;
                    opcao.hidden = !visivel;
                    algumVisivel = algumVisivel || visivel;
                });
                grupo.hidden = !algumVisivel;
            });
        });
    });

    // Árvore da estrutura: filtro por nome e expandir/recolher.
    var arvore = document.getElementById('arvore');
    if (arvore) {
        var detalhes = arvore.querySelectorAll('details');

        document.querySelectorAll('[data-arvore-acao]').forEach(function (botao) {
            botao.addEventListener('click', function () {
                var abrir = botao.getAttribute('data-arvore-acao') === 'expandir';
                detalhes.forEach(function (d) { d.open = abrir; });
            });
        });

        var filtro = document.getElementById('filtro-arvore');
        var temporizador;
        filtro.addEventListener('input', function () {
            clearTimeout(temporizador);
            temporizador = setTimeout(function () { filtrarArvore(normalizar(filtro.value.trim())); }, 200);
        });

        var filtrarArvore = function (termo) {
            arvore.querySelectorAll('li').forEach(function (li) { li.hidden = false; });
            if (termo === '') {
                detalhes.forEach(function (d) { d.open = false; });
                return;
            }

            // Percorre de baixo para cima: um item fica visível se ele ou algum descendente combinar.
            var itens = Array.prototype.slice.call(arvore.querySelectorAll('li')).reverse();
            itens.forEach(function (li) {
                var rotulo = li.querySelector('.struct-label');
                var combina = rotulo && normalizar(rotulo.textContent).indexOf(termo) !== -1;
                var filhoVisivel = li.querySelector('ul > li:not([hidden])') !== null;
                li.hidden = !(combina || filhoVisivel);
                var d = li.querySelector(':scope > details');
                if (d) {
                    d.open = filhoVisivel;
                }
            });
        };
    }

    function normalizar(texto) {
        return (texto || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
    }
})();
