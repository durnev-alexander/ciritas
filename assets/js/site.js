document.addEventListener('DOMContentLoaded',()=>{
  document.documentElement.classList.add('js');
  const buttons=[...document.querySelectorAll('[data-theme-value]')];
  const applyTheme=(theme)=>{
    const value=theme==='dark'?'dark':'light';
    document.documentElement.dataset.theme=value;
    try{localStorage.setItem('ciritas_theme',value)}catch(e){}
    buttons.forEach(button=>{
      const active=button.dataset.themeValue===value;
      button.classList.toggle('active',active);
      button.setAttribute('aria-pressed',active?'true':'false');
    });
  };
  applyTheme(document.documentElement.dataset.theme||'light');
  buttons.forEach(button=>button.addEventListener('click',()=>applyTheme(button.dataset.themeValue)));
});
