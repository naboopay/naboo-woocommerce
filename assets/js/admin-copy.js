document.addEventListener('DOMContentLoaded', function () {
    // Sélecteur par ID (correspond à ton HTML fourni)
    var input = document.getElementById('woocommerce_naboopay_webhook_url');
    if (!input) return;

    // éviter duplication si le script est chargé plusieurs fois
    if (document.querySelector('.naboopay-copy-btn')) return;

    // créer le bouton
    var btn = document.createElement('button');
    btn.type = 'button'; // important: pas de submit
    btn.className = 'button nabopay-copy-btn nabopay-copy-btn--small';
    btn.setAttribute('aria-label', 'Copier l\'URL du webhook');
    btn.textContent = 'Copier';
    btn.style.marginLeft = '8px';
    btn.style.verticalAlign = 'middle';

    // Insérer le bouton juste après l'input (aprèsend garantit position immédiate)
    input.insertAdjacentElement('afterend', btn);

    // Optionnel : petit indicateur visuel (accessible)
    var live = document.createElement('span');
    live.setAttribute('aria-live', 'polite');
    live.style.position = 'absolute';
    live.style.left = '-9999px';
    btn.insertAdjacentElement('afterend', live);

    btn.addEventListener('click', function (e) {
        e.preventDefault(); // empêche tout comportement par défaut
        e.stopPropagation();

        var text = input.value || '';

        // si navigator.clipboard disponible (sécurisé)
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function () {
                btn.textContent = 'Copié !';
                live.textContent = 'URL du webhook copiée';
                setTimeout(function () {
                    btn.textContent = 'Copier';
                    live.textContent = '';
                }, 1400);
            }).catch(function () {
                fallbackCopy(input, btn, live);
            });
        } else {
            // fallback pour anciens navigateurs
            fallbackCopy(input, btn, live);
        }
    });

    function fallbackCopy(inputEl, btnEl, liveEl) {
        // enlever readonly temporairement si nécessaire pour select()
        var prevReadOnly = inputEl.hasAttribute('readonly');
        if (prevReadOnly) {
            inputEl.removeAttribute('readonly');
        }

        // sélectionner le texte
        try {
            inputEl.focus();
            inputEl.select();
            var successful = document.execCommand('copy');
            if (successful) {
                btnEl.textContent = 'Copié !';
                liveEl.textContent = 'URL du webhook copiée';
                setTimeout(function () {
                    btnEl.textContent = 'Copier';
                    liveEl.textContent = '';
                }, 1400);
            } else {
                alert('Impossible de copier l\'URL dans le presse-papier');
            }
        } catch (err) {
            alert('Impossible de copier l\'URL dans le presse-papier');
        } finally {
            // restaurer readonly si on l'a retiré
            if (prevReadOnly) {
                inputEl.setAttribute('readonly', 'readonly');
            }
            try { window.getSelection().removeAllRanges(); } catch (e) {}
        }
    }
});
