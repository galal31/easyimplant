document.addEventListener('click', event => {
    const choice = event.target.closest('[data-case-target]');
    if (!choice || choice.getAttribute('aria-pressed') === 'true') return;
    const template = document.getElementById(choice.dataset.caseTarget);
    const stage = document.getElementById('doctor-case-stage');
    if (!template || !stage) return;
    stage.replaceChildren(template.content.cloneNode(true));
    document.querySelectorAll('[data-case-target]').forEach(button => {
        button.setAttribute('aria-pressed', button === choice ? 'true' : 'false');
    });
    document.getElementById('case-selection-status').textContent = stage.querySelector('h3').textContent;
});
