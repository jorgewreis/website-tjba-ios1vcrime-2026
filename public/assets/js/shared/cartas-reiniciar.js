(function (window) {
    'use strict';
    window.App=window.App||{};
    function bind(ctx) {
        document.querySelectorAll('.js-reiniciar-carta').forEach(function(button){
            if(button.dataset.restartBound)return;
            button.dataset.restartBound='1';
            button.addEventListener('click',async function(){
                if(button.disabled)return;
                var cartaId=String(button.dataset.cartaId||'').trim();
                var module=String(button.dataset.modulo||'').trim();
                if(!cartaId||!module)return;
                var row=button.closest('.register-line');
                var controls=Array.from((row||button.parentElement).querySelectorAll('.js-reiniciar-carta,.js-continuar-subtarefa,.js-mover-carta-recebida'));
                var states=controls.map(function(control){return control.disabled;});
                controls.forEach(function(control){control.disabled=true;});
                try {
                    var response=await fetch('/api/cartorio-cartas/reiniciar.php',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:JSON.stringify({carta_id:cartaId,modulo:module})});
                    var data=await response.json();
                    if(!response.ok||!data.success)throw new Error(data.error||'Não foi possível reiniciar a carta.');
                    ctx.queuePostReloadToast('success','Sucesso',module==='expedidas'?'Carta reiniciada: aguardando envio.':'Carta reiniciada: aguardando cumprimento.');
                    window.location.reload();
                } catch(error) {
                    ctx.notify('error','Erro',error.message||'Não foi possível reiniciar a carta.');
                    controls.forEach(function(control,index){control.disabled=states[index];});
                }
            });
        });
    }
    window.App.CartasReiniciar={bind:bind};
})(window);
