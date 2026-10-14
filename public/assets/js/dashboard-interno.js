(function(){
  'use strict';
  const $=id=>document.getElementById(id), charts=[];
  const colors={purple:'#7950ad',mint:'#4f9f88',blue:'#5b8fca',orange:'#d99a39',red:'#ca6868'};
  const countBadgeClasses=['dash-count-mint','dash-count-purple','dash-count-warning','dash-count-danger'];
  if(window.Chart) Chart.defaults.font.family="'Google Sans', sans-serif";

  function set(id,value){const el=$(id);if(el)el.textContent=value;}
  function count(value){return value===null||value===undefined?'—':Number(value).toLocaleString('pt-BR');}
  function applyCountBadge(element,value){
    if(!element)return;
    element.classList.remove(...countBadgeClasses);
    if(value===null||value===undefined||value==='')return;
    const number=Number(value);
    if(!Number.isFinite(number))return;
    const tier=number<4?'dash-count-mint':number<=7?'dash-count-purple':number<=10?'dash-count-warning':'dash-count-danger';
    element.classList.add(tier);
  }
  function showPositiveMetric(rowId,value){
    const row=$(rowId),number=Number(value);
    if(row)row.hidden=value===null||value===undefined||value===''||!Number.isFinite(number)||number<=0;
    return value!==null&&value!==undefined&&value!==''&&Number.isFinite(number)&&number>0;
  }
  function metricNumber(element){
    const text=(element?.textContent||'').trim().replace(/\./g,'').replace(',','.');
    const value=Number(text);
    return Number.isFinite(value)?value:0;
  }
  function sortSectorMetrics(){
    const container=$('dash-cartorio-queue');if(!container)return;
    const rows=[...container.querySelectorAll(':scope > .dash-sector-metric')].filter(row=>!row.hidden);
    rows.sort((a,b)=>{
      const overdueA=a.classList.contains('dash-sector-overdue'),overdueB=b.classList.contains('dash-sector-overdue');
      if(overdueA!==overdueB)return overdueA?-1:1;
      if(overdueA){
        const daysA=Number(a.dataset.urgencyDays)||0,daysB=Number(b.dataset.urgencyDays)||0;
        if(daysA!==daysB)return daysB-daysA;
      }
      return metricNumber(b.querySelector('strong'))-metricNumber(a.querySelector('strong'));
    });
    rows.forEach(row=>container.appendChild(row));
  }
  function destroyCharts(){charts.splice(0).forEach(c=>c.destroy());}
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
    [...(items||[])].sort((a,b)=>{
      const overdueA=a.key==='cumprimento_expedido_excedido',overdueB=b.key==='cumprimento_expedido_excedido';
      if(overdueA!==overdueB)return overdueA?-1:1;
      return Number(b.value)-Number(a.value);
    }).forEach(item=>{
      if(!showPositiveMetric(null,item.value))return;
      const card=document.createElement('article');card.className='dash-card dash-alert-card';card.dataset.alertKey=item.key;
      if(item.color==='mint'||item.color==='warm')card.classList.add('dash-alert-'+item.color);
      const copy=document.createElement('div');copy.className='dash-alert-copy';
      const label=document.createElement('strong');label.textContent=item.label;
      const detail=document.createElement('span');detail.textContent=item.detail;
      const value=document.createElement('span');value.className='dash-alert-number';
      value.textContent=count(item.value)+(item.unit==='days'?' '+(Number(item.value)===1?'dia':'dias'):'');
      applyCountBadge(value,item.value);
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
      weeklyBars('dash-chart-cadastros-semana',week.labels||[],[
        {label:'Expedidas',data:week.expedidas||[],backgroundColor:colors.purple,borderRadius:3,maxBarThickness:10},
        {label:'Recebidas',data:week.recebidas||[],backgroundColor:colors.mint,borderRadius:3,maxBarThickness:10}
      ]);

      const offices=data.geral?.oficios_cadastrados||{};
      const officesYear=offices.ano||{};
      const officesWeek=offices.semana||{labels:[],cartorio:[],gabinete:[]};
      set('dash-offices-year-cartorio',count(officesYear.cartorio));
      set('dash-offices-year-gabinete',count(officesYear.gabinete));
      weeklyBars('dash-chart-oficios-semana',officesWeek.labels||[],[
        {label:'Cartório',data:officesWeek.cartorio||[],backgroundColor:colors.purple,borderRadius:3,maxBarThickness:10},
        {label:'Gabinete',data:officesWeek.gabinete||[],backgroundColor:colors.mint,borderRadius:3,maxBarThickness:10}
      ]);

      const cartorioEnabled=Boolean(data.setor?.conectado)&&(data.setor?.areas||[]).includes('CARTORIO');
      const queueCard=$('dash-cartorio-queue');
      const sectorQueues=data.setor?.filas||[];
      const queueExpedidas=data.setor?.conectado?activeQueueCount(sectorQueues,'cartas_expedidas','aguardando_envio'):null;
      const queueRecebidas=data.setor?.conectado?activeQueueCount(sectorQueues,'cartas_recebidas','devolucao'):null;
      const queueOficios=data.setor?.conectado?activeQueueCount(sectorQueues,'oficios','enviar_oficio'):null;
      const queueEditais=data.setor?.conectado?activeQueueCount(sectorQueues,'editais','certificacao_publicacao'):null;
      set('dash-queue-expedidas',count(queueExpedidas));
      set('dash-queue-recebidas',count(queueRecebidas));
      set('dash-queue-oficios',count(queueOficios));
      set('dash-queue-editais',count(queueEditais));
      const hasExpedidasMetric=showPositiveMetric('dash-queue-expedidas-row',queueExpedidas);
      const hasRecebidasMetric=showPositiveMetric('dash-queue-recebidas-row',queueRecebidas);
      const oficiosRow=$('dash-queue-oficios-row');
      if(oficiosRow)oficiosRow.hidden=!cartorioEnabled||queueOficios===null||queueOficios===undefined;
      const hasOficiosMetric=Boolean(cartorioEnabled&&queueOficios!==null&&queueOficios!==undefined);
      const hasEditaisMetric=showPositiveMetric('dash-queue-editais-row',queueEditais);
      let hasAnyMetric=hasExpedidasMetric||hasRecebidasMetric||hasOficiosMetric||hasEditaisMetric;
      applyCountBadge($('dash-queue-expedidas'),queueExpedidas);
      applyCountBadge($('dash-queue-recebidas'),queueRecebidas);
      applyCountBadge($('dash-queue-oficios'),queueOficios);
      applyCountBadge($('dash-queue-editais'),queueEditais);
      const overdue=data.setor?.prazos_vencidos||{};
      const overdueDays=data.setor?.prazos_vencidos_dias||{};
      [['expedidas','dash-overdue-expedidas-row','dash-overdue-expedidas'],['recebidas','dash-overdue-recebidas-row','dash-overdue-recebidas']].forEach(([key,rowId,valueId])=>{
        const value=overdue[key];
        const row=$(rowId),days=overdueDays[key];
        if(row){if(days===null||days===undefined)delete row.dataset.urgencyDays;else row.dataset.urgencyDays=String(days);}
        hasAnyMetric=showPositiveMetric(rowId,value)||hasAnyMetric;
        if(value!==null&&value!==undefined)set(valueId,count(value));
        applyCountBadge($(valueId),value);
      });
      sortSectorMetrics();
      if(queueCard)queueCard.hidden=!cartorioEnabled||!hasAnyMetric;

      const modules=data.usuario?.modules||[];
      set('dash-done-expedidas',count(moduleTotal(modules,'cartas_expedidas')));
      set('dash-done-recebidas',count(moduleTotal(modules,'cartas_recebidas')));
      set('dash-done-oficios',count(data.usuario?.oficios_expedidos_30d));
      set('dash-done-medidas-protetivas',count(data.usuario?.medidas_protetivas_expedidas_30d));

      renderAlerts(data.alertas);
    }catch(error){
      if($('dashboard-erro')){$('dashboard-erro').textContent=error.message||'Não foi possível carregar os indicadores.';$('dashboard-erro').hidden=false;}
    }
  }

  document.addEventListener('DOMContentLoaded',()=>{
    load();
  });
})();
