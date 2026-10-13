(function(){
  'use strict';
  const $=id=>document.getElementById(id), charts=[];
  const colors={purple:'#7950ad',mint:'#4f9f88',blue:'#5b8fca',orange:'#d99a39',red:'#ca6868'};
  if(window.Chart) Chart.defaults.font.family="'Google Sans', sans-serif";

  function set(id,value){const el=$(id);if(el)el.textContent=value;}
  function count(value){return value===null||value===undefined?'—':Number(value).toLocaleString('pt-BR');}
  function recentRegistrations(values){const total=(values||[]).reduce((sum,value)=>sum+Number(value),0);return count(total)+' '+(total===1?'cadastrada':'cadastradas')+' em 7 dias';}
  function destroyCharts(){charts.splice(0).forEach(c=>c.destroy());}
  function spark(id,labels,values,color){
    const el=$(id);if(!el||!window.Chart)return;
    const hasMovement=values.some(value=>Number(value)>0);
    el.parentElement.hidden=!hasMovement;
    if(!hasMovement)return;
    charts.push(new Chart(el,{type:'bar',data:{labels,datasets:[{data:values,backgroundColor:values.map((_,i)=>i===values.length-1?color:color+'88'),borderWidth:0,borderRadius:2,maxBarThickness:8}]},options:{responsive:true,maintainAspectRatio:false,animation:false,plugins:{legend:{display:false},tooltip:{enabled:true,displayColors:false}},scales:{x:{display:false},y:{display:false,beginAtZero:true}},layout:{padding:1}}}));
  }
  function weeklyBars(id,labels,datasets){
    const el=$(id);if(!el||!window.Chart)return;
    charts.push(new Chart(el,{type:'bar',data:{labels,datasets},options:{responsive:true,maintainAspectRatio:false,animation:false,interaction:{mode:'index',intersect:false},plugins:{legend:{display:false}},scales:{x:{grid:{display:false},border:{display:false},ticks:{color:'#777183',maxRotation:0,autoSkip:false,padding:1,font:{size:8}}},y:{beginAtZero:true,ticks:{precision:0,maxTicksLimit:3,padding:2,color:'#777183',font:{size:8}},border:{display:false},grid:{color:'rgba(39,31,50,.06)'}}}}}));
  }
  function activeQueueCount(queues,key,type){
    const broken=(queues||[]).some(row=>row.key===key&&row.error);
    if(broken)return null;
    const row=(queues||[]).find(item=>item.key===key&&item.type===type);
    return row?row.count:0;
  }
  function moduleTotal(modules,module){const row=(modules||[]).find(item=>item.modulo===module);return row?Number(row.total):0;}
  function renderAlerts(items){
    const container=$('dash-alerts');if(!container)return;
    container.replaceChildren();
    (items||[]).forEach(item=>{
      const card=document.createElement('article');card.className='dash-card dash-alert-card';card.dataset.alertKey=item.key;
      if(item.color==='mint'||item.color==='warm')card.classList.add('dash-alert-'+item.color);
      const copy=document.createElement('div');copy.className='dash-alert-copy';
      const label=document.createElement('strong');label.textContent=item.label;
      const detail=document.createElement('span');detail.textContent=item.detail;
      const value=document.createElement('span');value.className='dash-alert-number';
      value.textContent=count(item.value)+(item.unit==='days'?' '+(Number(item.value)===1?'dia':'dias'):'');
      copy.append(label,detail);card.append(copy,value);container.append(card);
    });
  }

  async function load(){
    const error=$('dashboard-erro');if(error)error.hidden=true;
    const url='/api/dashboard/resumo.php';
    try{
      const response=await fetch(url,{credentials:'same-origin',headers:{Accept:'application/json'},cache:'no-store'});
      const data=await response.json();
      if(!response.ok)throw new Error(data.error||'Não foi possível carregar os indicadores.');
      destroyCharts();

      const registered=data.geral?.cartas_cadastradas||{};
      const week=registered.semana||{labels:[],expedidas:[],recebidas:[]};
      set('dash-registered-expedidas',count(registered.expedidas));
      set('dash-registered-recebidas',count(registered.recebidas));
      set('dash-week-expedidas',recentRegistrations(week.expedidas));
      set('dash-week-recebidas',recentRegistrations(week.recebidas));
      weeklyBars('dash-chart-cadastros-semana',week.labels||[],[
        {label:'Expedidas',data:week.expedidas||[],backgroundColor:colors.purple,borderRadius:3,maxBarThickness:10},
        {label:'Recebidas',data:week.recebidas||[],backgroundColor:colors.mint,borderRadius:3,maxBarThickness:10}
      ]);

      const cartorioEnabled=Boolean(data.setor?.conectado)&&(data.setor?.areas||[]).includes('CARTORIO');
      const queueCard=$('dash-cartorio-queue');
      if(queueCard)queueCard.hidden=!cartorioEnabled;
      const sectorQueues=data.setor?.filas||[];
      set('dash-queue-expedidas',data.setor?.conectado?count(activeQueueCount(sectorQueues,'cartas_expedidas','aguardando_envio')):'—');
      set('dash-queue-recebidas',data.setor?.conectado?count(activeQueueCount(sectorQueues,'cartas_recebidas','devolucao')):'—');

      const modules=data.usuario?.modules||[];
      set('dash-done-expedidas',count(moduleTotal(modules,'cartas_expedidas')));
      set('dash-done-recebidas',count(moduleTotal(modules,'cartas_recebidas')));
      const weeklyModules=data.usuario?.weekly_by_module||{};
      const labels=week.labels||[];
      spark('dash-spark-user-expedidas',labels,weeklyModules.cartas_expedidas||[],colors.purple);
      spark('dash-spark-user-recebidas',labels,weeklyModules.cartas_recebidas||[],colors.mint);

      renderAlerts(data.alertas);
    }catch(error){
      if($('dashboard-erro')){$('dashboard-erro').textContent=error.message||'Não foi possível carregar os indicadores.';$('dashboard-erro').hidden=false;}
    }
  }

  document.addEventListener('DOMContentLoaded',()=>{
    load();
  });
})();
