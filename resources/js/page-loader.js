document.addEventListener('DOMContentLoaded',()=>{
 const loader=document.querySelector('#pageLoader');if(!loader)return;
 const reduced=matchMedia('(prefers-reduced-motion: reduce)').matches;
 const arrivedFromTransition=sessionStorage.getItem('mitsubishi-crm-transitioning')==='1';
 sessionStorage.removeItem('mitsubishi-crm-transitioning');
 const show=()=>{loader.classList.add('is-visible');loader.setAttribute('aria-hidden','false')};
 const hide=()=>{loader.classList.remove('is-visible');loader.setAttribute('aria-hidden','true')};
 setTimeout(hide,reduced?90:(arrivedFromTransition?110:520));
 setTimeout(hide,700);
 document.addEventListener('click',event=>{
  const link=event.target.closest('a[href]');if(!link||event.defaultPrevented||event.button!==0||event.metaKey||event.ctrlKey||event.shiftKey||event.altKey)return;
  if(link.hasAttribute('download')||link.target==='_blank'||link.dataset.noTransition!==undefined)return;
  const destination=new URL(link.href,location.href);
  if(destination.origin!==location.origin||destination.protocol==='mailto:'||destination.protocol==='tel:')return;
  if(destination.pathname===location.pathname&&destination.search===location.search&&(destination.hash||link.getAttribute('href')?.startsWith('#')))return;
  event.preventDefault();show();sessionStorage.setItem('mitsubishi-crm-transitioning','1');
  setTimeout(()=>location.assign(destination.href),reduced?70:330);
 });
 window.addEventListener('pageshow',event=>{if(event.persisted)setTimeout(hide,60)});
});
