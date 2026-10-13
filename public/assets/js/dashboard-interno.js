(function(){
  'use strict';
  const $=id=>document.getElementById(id), charts=[];
  const colors={purple:'#7950ad',mint:'#4f9f88',blue:'#5b8fca',orange:'#d99a39',red:'#ca6868'};
  const areaNames={CARTORIO:'Cartório',GABINETE:'Gabinete',AUDIENCIAS:'Sala de Audiências'};
  if(window.Chart) Chart.defaults.font.family="'Google Sans', sans-serif";

  function set(id,value){const el=$(id);if(el)el.textContent=value;}
  function count(value){return value===null||value===undefined?'—':Number(value).toLocaleString('pt-BR');}
  function destroyCharts(){charts.splice(0).forEach(c=>c.destroy());}
  function spark(id,labels,values,color){
    const el=$(id);if(!el||!window.Chart)return;
    charts.push(new Chart(el,{type:'line',data:{labels,datasets:[{data:values,borderColor:color,backgroundColor:color+'22',borderWidth:2,pointRadius:0,tension:.42,fill:true}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false},tooltip:{enabled:false}},scales:{x:{display:false},y:{display:false,beginAtZero:true}},layout:{padding:2}}}));
  }
  function line(id,labels,datasets){
    const el=$(id);if(!el||!window.Chart)return;
    charts.push(new Chart(el,{type:'line',data:{labels,datasets},options:{responsive:true,maintainAspectRatio:false,interaction:{mode:'index',intersect:false},plugins:{legend:{display:false}},scales:{x:{grid:{display:false},ticks:{color:'#777183',maxRotation:0,autoSkip:false,font:{size:10}}},y:{beginAtZero:true,ticks:{precision:0,color:'#777183',font:{size:10}},grid:{color:'rgba(39,31,50,.08)'}}}}}));
  }
  function activeQueueCount(queues,key,type){
    const broken=(queues||[]).some(row=>row.key===key&&row.error);
    if(broken)return null;
    const row=(queues||[]).find(item=>item.key===key&&item.type===type);
    return row?row.count:0;
  }
  function moduleTotal(modules,module){const row=(modules||[]).find(item=>item.modulo===module);return row?Number(row.total):0;}
  function ageInDays(value){
    if(!value)return null;
    const parsed=new Date(String(value).replace(' ','T'));
    if(Number.isNaN(parsed.getTime()))return null;
    const today=new Date();today.setHours(0,0,0,0);parsed.setHours(0,0,0,0);
    return Math.max(0,Math.floor((today-parsed)/86400000));
  }

  async function load(sector){
    const error=$('dashboard-erro');if(error)error.hidden=true;
    const url='/api/dashboard/resumo.php'+(sector?'?setor='+encodeURIComponent(sector):'');
    try{
      const response=await fetch(url,{credentials:'same-origin',headers:{Accept:'application/json'},cache:'no-store'});
      const data=await response.json();
      if(!response.ok)throw new Error(data.error||'Não foi possível carregar os indicadores.');
      destroyCharts();

      const registered=data.geral?.cartas_cadastradas||{};
      const week=registered.semana||{labels:[],expedidas:[],recebidas:[]};
      set('dash-registered-expedidas',count(registered.expedidas));
      set('dash-registered-recebidas',count(registered.recebidas));
      spark('dash-spark-expedidas',week.labels||[],week.expedidas||[],colors.purple);
      spark('dash-spark-recebidas',week.labels||[],week.recebidas||[],colors.mint);
      line('dash-chart-cadastros-semana',week.labels||[],[
        {label:'Expedidas',data:week.expedidas||[],borderColor:colors.purple,backgroundColor:colors.purple+'22',borderWidth:2,pointRadius:2,pointBackgroundColor:colors.purple,tension:.36,fill:true},
        {label:'Recebidas',data:week.recebidas||[],borderColor:colors.mint,backgroundColor:colors.mint+'22',borderWidth:2,pointRadius:2,pointBackgroundColor:colors.mint,tension:.36,fill:true}
      ]);

      const selected=data.setor?.selecionado||'';
      const areas=data.setor?.areas||[];
      const selector=$('dash-sector-filter');
      if(selector){
        const current=sector||selected||selector.value;
        selector.innerHTML='<option value="">Todas as áreas autorizadas</option>'+areas.map(area=>'<option value="'+area+'">'+(areaNames[area]||area)+'</option>').join('');
        selector.value=areas.includes(current)?current:'';
      }
      set('dash-areas',data.setor?.conectado?(selected?(areaNames[selected]||selected):'Todas as áreas autorizadas'):'Setor sem indicadores de cartas');
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

      const age=ageInDays(data.fila_mais_antiga_em);
      set('dash-oldest-days',age===null?'—':age+' '+(age===1?'dia':'dias'));
      set('dash-oldest-label',data.fila_mais_antiga_etapa||'Sem cartas pendentes na fila');
      set('dash-deadlines-soon',count(data.prazos_proximos_7d));
      set('dash-waiting-returns',count(data.cartas_recebidas_aguardando_devolucao));
    }catch(error){
      if($('dashboard-erro')){$('dashboard-erro').textContent=error.message||'Não foi possível carregar os indicadores.';$('dashboard-erro').hidden=false;}
    }
  }

  document.addEventListener('DOMContentLoaded',()=>{
    load('');
    const selector=$('dash-sector-filter');
    if(selector)selector.addEventListener('change',event=>load(event.target.value));
  });
})();
