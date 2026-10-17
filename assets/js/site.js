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

  const liveSearchForm=document.querySelector('form[data-live-search]');
  const liveSearchResults=document.querySelector('[data-live-search-results]');
  if(liveSearchForm&&liveSearchResults){
    const searchInput=liveSearchForm.querySelector('input[name="q"]');
    let searchTimer=0;
    let searchController=null;
    const updateSearch=async()=>{
      const url=new URL(window.location.href);
      const query=(searchInput&&searchInput.value||'').trim();
      if(query)url.searchParams.set('q',query);else url.searchParams.delete('q');
      if(searchController)searchController.abort();
      searchController=new AbortController();
      try{
        const response=await fetch(url.toString(),{credentials:'same-origin',signal:searchController.signal,headers:{'X-Requested-With':'live-search'}});
        if(!response.ok)throw new Error('search');
        const html=await response.text();
        const doc=new DOMParser().parseFromString(html,'text/html');
        const next=doc.querySelector('[data-live-search-results]');
        if(!next)throw new Error('search-results');
        liveSearchResults.replaceChildren(...[...next.childNodes].map(node=>node.cloneNode(true)));
        window.history.replaceState({},'',url.pathname+url.search+url.hash);
      }catch(error){
        if(error&&error.name==='AbortError')return;
      }
    };
    liveSearchForm.addEventListener('submit',event=>{event.preventDefault();clearTimeout(searchTimer);updateSearch();});
    if(searchInput){
      searchInput.addEventListener('input',()=>{
        clearTimeout(searchTimer);
        searchTimer=setTimeout(updateSearch,250);
      });
    }
  }

  const licenseForms=[...document.querySelectorAll('form.inline-form[action="cart.php"]')].filter(form=>form.querySelector('input[name="action"][value="add"]')&&form.querySelector('input[name="price_id"]'));
  if(!licenseForms.length)return;

  const setCsrf=value=>{
    if(!value)return;
    document.querySelectorAll('input[name="_csrf"]').forEach(input=>{input.value=value;});
  };
  const applyState=state=>{
    setCsrf(state.csrf||'');
    licenseForms.forEach(form=>{
      const priceId=String(parseInt(form.querySelector('input[name="price_id"]').value||'0',10));
      const item=(state.items||{})[priceId]||null;
      const qty=form.querySelector('input[name="amount"]');
      const button=form.querySelector('button[type="submit"]');
      if(item){
        form.dataset.cartItemId=String(item.item_id);
        if(qty)qty.value=String(item.amount);
        if(button){button.textContent='В корзине';button.classList.add('btn-in-cart');}
      }else{
        form.dataset.cartItemId='';
        if(button){button.textContent='В корзину';button.classList.remove('btn-in-cart');}
      }
    });
  };
  const request=async params=>{
    params.set('_ajax','1');
    const response=await fetch('cart.php',{method:'POST',credentials:'same-origin',headers:{'Accept':'application/json','Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'},body:params.toString()});
    const data=await response.json();
    if(!response.ok||!data.ok)throw new Error(data.message||'Не удалось изменить корзину.');
    applyState(data);
    return data;
  };

  fetch('cart.php?ajax=state',{credentials:'same-origin',headers:{'Accept':'application/json'}})
    .then(response=>response.ok?response.json():Promise.reject())
    .then(data=>{if(data&&data.ok)applyState(data);})
    .catch(()=>{});

  licenseForms.forEach(form=>{
    const priceInput=form.querySelector('input[name="price_id"]');
    const qty=form.querySelector('input[name="amount"]');
    const csrf=form.querySelector('input[name="_csrf"]');
    let timer=0;

    form.addEventListener('submit',event=>{
      event.preventDefault();
      if(form.dataset.cartItemId)return;
      const amount=Math.max(1,Math.min(999,parseInt(qty&&qty.value||'1',10)||1));
      if(qty)qty.value=String(amount);
      const params=new URLSearchParams();
      params.set('_csrf',csrf?csrf.value:'');
      params.set('action','add');
      params.set('price_id',priceInput?priceInput.value:'0');
      params.set('amount',String(amount));
      request(params).catch(()=>{});
    });

    if(qty){
      qty.addEventListener('input',()=>{
        if(!form.dataset.cartItemId)return;
        clearTimeout(timer);
        timer=setTimeout(()=>{
          const amount=parseInt(qty.value||'0',10);
          if(!Number.isFinite(amount)||amount<1||amount>999)return;
          const params=new URLSearchParams();
          params.set('_csrf',csrf?csrf.value:'');
          params.set('action','update');
          params.set('item_id',form.dataset.cartItemId);
          params.set('amount',String(amount));
          request(params).catch(()=>{});
        },250);
      });
      qty.addEventListener('change',()=>{
        if(!form.dataset.cartItemId)return;
        const amount=Math.max(1,Math.min(999,parseInt(qty.value||'1',10)||1));
        qty.value=String(amount);
      });
    }
  });
});
