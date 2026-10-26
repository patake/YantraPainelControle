// Formulários HTML comuns (login, 2FA, senha): ao enviar, o botão fica desativado e mostra o
// ícone de carregamento do Flux. O Livewire já faz isso nos formulários com wire:submit.
document.addEventListener('submit', (event) => {
    const form = event.target;

    if (!(form instanceof HTMLFormElement) || form.hasAttribute('wire:submit') || event.defaultPrevented) {
        return;
    }

    // Desativa depois do envio começar: um botão desativado antes sairia dos dados do formulário.
    setTimeout(() => {
        form.querySelectorAll('button[type="submit"]').forEach((button) => {
            button.disabled = true;
        });
    });
});

// Ao voltar para a página pelo botão Voltar do navegador, o botão volta a ficar ativo.
window.addEventListener('pageshow', (event) => {
    if (event.persisted) {
        document.querySelectorAll('form button[type="submit"][disabled]').forEach((button) => {
            button.disabled = false;
        });
    }
});
