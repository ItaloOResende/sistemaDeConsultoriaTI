(function () {
    'use strict';

    function digits(value) {
        return (value || '').replace(/\D/g, '');
    }

    function maskPhone(value) {
        const d = digits(value).slice(0, 11);
        if (d.length <= 10) {
            return d.replace(/^(\d{0,2})(\d{0,4})(\d{0,4}).*/, function (_, a, b, c) {
                let out = a ? '(' + a + (a.length === 2 ? ') ' : '') : '';
                if (b) out += b;
                if (c) out += '-' + c;
                return out;
            });
        }
        return d.replace(/^(\d{2})(\d{5})(\d{0,4}).*/, '($1) $2-$3');
    }

    function maskCep(value) {
        const d = digits(value).slice(0, 8);
        return d.length > 5 ? d.slice(0, 5) + '-' + d.slice(5) : d;
    }

    function maskCpf(value) {
        const d = digits(value).slice(0, 11);
        return d.replace(/^(\d{0,3})(\d{0,3})(\d{0,3})(\d{0,2}).*/, function (_, a, b, c, e) {
            let out = a || '';
            if (b) out += '.' + b;
            if (c) out += '.' + c;
            if (e) out += '-' + e;
            return out;
        });
    }

    function applyMask(input, fn) {
        input.addEventListener('input', function () {
            const pos = input.selectionStart;
            const before = digits(input.value).length;
            input.value = fn(input.value);
            const after = digits(input.value).length;
            let newPos = pos + (after - before);
            if (newPos < 0) newPos = 0;
            try {
                input.setSelectionRange(newPos, newPos);
            } catch (err) {
                /* navegador antigo */
            }
        });
    }

    document.querySelectorAll('input[name="telefone"]').forEach(function (el) {
        applyMask(el, maskPhone);
    });
    document.querySelectorAll('input[name="cep"]').forEach(function (el) {
        applyMask(el, maskCep);
    });
    document.querySelectorAll('input[name="cpf"]').forEach(function (el) {
        applyMask(el, maskCpf);
    });

    // Preenchimento automático via ViaCEP apenas em formulários marcados com data-cep.
    document.querySelectorAll('input[name="cep"][data-cep]').forEach(function (cepInput) {
        const form = cepInput.form;
        if (!form) return;

        function campo(name) {
            return form.querySelector('[name="' + name + '"]');
        }

        const endereco = campo('endereco');
        const numero = campo('numero');
        const bairro = campo('bairro');
        const cidade = campo('cidade');
        const estado = campo('estado');

        let status = form.querySelector('.cep-status');
        if (!status) {
            status = document.createElement('span');
            status.className = 'cep-status';
            cepInput.insertAdjacentElement('afterend', status);
        }

        function liberarCampos() {
            [endereco, bairro, cidade].forEach(function (el) {
                if (el) el.readOnly = false;
            });
            if (estado) {
                estado.disabled = false;
                const hidden = form.querySelector('.cep-estado-hidden');
                if (hidden) hidden.remove();
            }
        }

        function preencher(dados) {
            if (endereco) {
                endereco.value = dados.logradouro || '';
                endereco.readOnly = true;
            }
            if (bairro) {
                bairro.value = dados.bairro || '';
                bairro.readOnly = true;
            }
            if (cidade) {
                cidade.value = dados.localidade || '';
                cidade.readOnly = true;
            }
            if (estado && dados.uf) {
                estado.value = dados.uf;
                estado.disabled = true;
                let hidden = form.querySelector('.cep-estado-hidden');
                if (!hidden) {
                    hidden = document.createElement('input');
                    hidden.type = 'hidden';
                    hidden.className = 'cep-estado-hidden';
                    hidden.name = 'estado';
                    estado.insertAdjacentElement('afterend', hidden);
                }
                hidden.value = dados.uf;
            }
            status.textContent = 'Endereço encontrado. Número e complemento continuam editáveis.';
            status.className = 'cep-status cep-status-ok';
            if (numero) numero.focus();
        }

        function buscar() {
            const cep = digits(cepInput.value);
            if (cep.length !== 8) {
                status.textContent = '';
                status.className = 'cep-status';
                liberarCampos();
                return;
            }
            status.textContent = 'Buscando endereço...';
            status.className = 'cep-status';
            fetch('https://viacep.com.br/ws/' + cep + '/json/')
                .then(function (response) {
                    if (!response.ok) throw new Error('erro');
                    return response.json();
                })
                .then(function (dados) {
                    if (dados.erro) throw new Error('erro');
                    preencher(dados);
                })
                .catch(function () {
                    liberarCampos();
                    status.textContent = 'Não foi possível localizar este CEP. Preencha o endereço manualmente.';
                    status.className = 'cep-status cep-status-erro';
                });
        }

        cepInput.addEventListener('blur', buscar);
        cepInput.addEventListener('input', function () {
            if (digits(cepInput.value).length !== 8) {
                liberarCampos();
                status.textContent = '';
                status.className = 'cep-status';
            }
        });
    });
})();
