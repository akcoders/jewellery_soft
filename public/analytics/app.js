(() => {
  'use strict';
  const TOKEN_KEY = 'aabhushan_intelligence_token';
  const USER_KEY = 'aabhushan_intelligence_user';
  const API = `${window.location.origin}/api/mobile`;
  const $ = (id) => document.getElementById(id);
  const state = { token: localStorage.getItem(TOKEN_KEY) || '', user: null, data: null, charts: {}, deferredInstall: null };
  const palette = ['#7259ff','#18b9cc','#e7529e','#ff914d','#1dbb83','#dda62d','#ef5d67','#4d8df5','#9a61e8','#39c39b','#ffbd48','#7d83a2'];
  const pageMeta = {
    overview:['EXECUTIVE OVERVIEW','Command Centre'], orders:['ORDER INTELLIGENCE','Orders'], purchases:['SPEND CONTROL','Purchase History'],
    requirements:['FULFILMENT CONTROL','Requirements'], inventory:['CAPITAL CONTROL','Inventory Pulse'], team:['PEOPLE INTELLIGENCE','People Performance'],
    relationships:['RELATIONSHIP INTELLIGENCE','Customers & Partners']
  };

  Chart.defaults.font.family = "'DM Sans', Arial, sans-serif";
  Chart.defaults.color = '#85879d';
  Chart.defaults.borderColor = 'rgba(228,229,239,.75)';

  async function api(path, options = {}) {
    const headers = { Accept: 'application/json', ...(options.headers || {}) };
    if (state.token) headers.Authorization = `Bearer ${state.token}`;
    if (options.body && typeof options.body !== 'string') {
      headers['Content-Type'] = 'application/json';
      options.body = JSON.stringify(options.body);
    }
    let response;
    try { response = await fetch(`${API}${path}`, { ...options, headers }); }
    catch (_) { throw new Error('Network unavailable. Check your connection and retry.'); }
    const type = response.headers.get('content-type') || '';
    const payload = type.includes('application/json') ? await response.json() : null;
    if (response.status === 401) { logout(false); throw new Error('Session expired. Please sign in again.'); }
    if (!response.ok) throw new Error(payload?.message || payload?.error || `Request failed (${response.status})`);
    return payload?.data ?? payload;
  }

  async function login(event) {
    event.preventDefault();
    const button = event.currentTarget.querySelector('button');
    $('loginError').textContent = '';
    button.disabled = true; button.querySelector('span').textContent = 'Authenticating…';
    try {
      const data = await api('/login', { method: 'POST', body: { email: $('email').value.trim(), password: $('password').value, device_name: 'Analytics PWA' } });
      if (!data?.user?.is_admin) throw new Error('Admin access is required for business analytics.');
      state.token = data.token; state.user = data.user;
      localStorage.setItem(TOKEN_KEY, state.token); localStorage.setItem(USER_KEY, JSON.stringify(state.user));
      openApp(); await loadAnalytics();
    } catch (error) { $('loginError').textContent = error.message; }
    finally { button.disabled = false; button.querySelector('span').textContent = 'Open Intelligence'; }
  }

  function openApp() {
    $('loginScreen').classList.add('hidden'); $('appShell').classList.remove('hidden');
    const name = state.user?.name || 'Admin'; $('userName').textContent = name; $('userInitial').textContent = name.charAt(0).toUpperCase();
  }

  function logout(callApi = true) {
    if (callApi && state.token) api('/logout', { method: 'POST', body: {} }).catch(() => {});
    state.token = ''; state.user = null; state.data = null; localStorage.removeItem(TOKEN_KEY); localStorage.removeItem(USER_KEY);
    Object.values(state.charts).forEach((chart) => chart.destroy()); state.charts = {};
    $('appShell').classList.add('hidden'); $('loginScreen').classList.remove('hidden'); $('profileMenu').classList.add('hidden');
  }

  async function restore() {
    if (!state.token) return;
    try { state.user = await api('/me'); if (!state.user?.is_admin) throw new Error('Admin required'); openApp(); await loadAnalytics(); }
    catch (_) { logout(false); }
  }

  async function loadAnalytics() {
    $('loadingState').classList.remove('hidden'); $('errorState').classList.add('hidden'); $('pages').classList.add('hidden');
    $('refreshButton').classList.add('spinning');
    try {
      state.data = await api(`/analytics?days=${$('periodSelect').value}`);
      render(state.data);
      $('loadingState').classList.add('hidden'); $('pages').classList.remove('hidden');
      $('lastUpdated').textContent = `Updated ${dateTime(state.data.generated_at)}`;
    } catch (error) {
      $('loadingState').classList.add('hidden'); $('errorState').classList.remove('hidden'); $('errorText').textContent = error.message;
    } finally { $('refreshButton').classList.remove('spinning'); }
  }

  function render(data) {
    const period = data.period || {};
    $('periodCaption').textContent = `${date(period.from)} — ${date(period.to)} · Compared with the previous ${period.days || 90} days`;
    renderOverview(data); renderOrders(data); renderPurchases(data); renderRequirements(data); renderInventory(data); renderTeam(data); renderRelationships(data);
  }

  function renderOverview(data) {
    const o = data.overview || {}, c = data.comparison || {}, f = data.financial || {}, inv = data.inventory || {};
    $('overviewKpis').innerHTML = [
      kpi('Orders', number(o.orders), trend(c.orders), '#7259ff'), kpi('Order value', money(o.order_value,true), trend(c.order_value), '#e7529e'),
      kpi('Completed', number(o.completed), `${number(o.completion_rate)}% completion`, '#1dbb83'), kpi('Avg order', money(o.average_order_value,true), trend(c.average_order_value), '#18b9cc'),
      kpi('Outstanding', money(o.balance,true), `${number(f.advance_coverage)}% advance cover`, '#ff914d'), kpi('Inventory', money(inv.total_value,true), `${number(inv.negative_skus)} negative SKU`, '#dda62d')
    ].join('');
    const team = data.employee_performance?.totals || {};
    const score = clamp(Math.round((number(o.completion_rate) * .36) + (number(team.on_time_rate) * .34) + 30 - Math.min(20,number(o.overdue)*2) - Math.min(12,number(inv.negative_skus)*2)),0,100);
    $('businessScore').style.setProperty('--score', `${score}%`); $('businessScore').querySelector('strong').textContent = score;
    $('healthMessage').textContent = score >= 85 ? 'Business is operating strongly' : score >= 65 ? 'Healthy with clear improvement areas' : 'Immediate operating attention advised';
    $('insightStrip').innerHTML = (data.insights || []).map((x) => `<div class="insight ${esc(x.severity)}"><i>${x.severity==='positive'?'✓':'!'}</i><div><strong>${esc(x.title)}</strong><span>${esc(x.action)}</span></div></div>`).join('');
    lineChart('businessPulseChart', rows(data.order_trend), 'period', [
      dataset('Order value','value','#7259ff',true,'y'), dataset('Advance','advance','#1dbb83',true,'y'), dataset('Orders','orders','#ff914d',false,'y1')
    ], { moneyAxis:true, dual:true });
    doughnut('orderStatusChart', data.order_status, 'status', 'count'); doughnut('dueHealthChart', data.orders_analysis?.due_health, 'name', 'count');
    bar('financialChart',['Order value','Purchases','Advance','Balance'],[f.order_value,f.purchase_value,f.advance_collected,f.order_balance],palette.slice(0,4),true);
    doughnut('inventoryMixChart', inv.mix, 'name', 'value'); horizontalBar('topCustomersChart', data.customers?.top, 'name', 'value', true);
    horizontalBar('teamLeadersChart', data.employee_performance?.ranking?.slice(0,8), 'name', 'score', false);
  }

  function renderOrders(data) {
    const o = data.overview || {}, a = data.orders_analysis || {};
    $('ordersHeroValue').textContent = money(o.order_value,true);
    $('orderKpis').innerHTML = [kpi('Orders',number(o.orders),'In selected period','#7259ff'),kpi('Completion',`${number(o.completion_rate)}%`,`${number(o.completed)} completed`,'#1dbb83'),kpi('Cycle time',`${number(o.average_cycle_days)} days`,'Average completion','#18b9cc'),kpi('Overdue',number(o.overdue),'Needs action','#ef5d67'),kpi('Receipt gap',number(o.receiving_pending),'Completed without receiving','#ff914d')].join('');
    lineChart('orderTrendChart', rows(data.order_trend), 'period', [dataset('Orders','orders','#7259ff',true,'y'),dataset('Completed','completed','#1dbb83',true,'y'),dataset('Order value','value','#e7529e',false,'y1')],{dual:true,moneySecondary:true});
    doughnut('receivingChart', a.receiving,'name','count'); doughnut('orderTypeChart',a.types,'name','count'); barRows('priorityChart',a.priorities,'name','count'); barRows('categoryChart',a.categories,'name','count');
    $('recentOrdersTable').innerHTML = tableRows(a.recent, (r) => `<tr><td><strong>${esc(r.order_no||'-')}</strong><small>${esc(r.order_name||'')}</small></td><td>${esc(r.customer_name||'-')}</td><td>${esc(r.order_type||'-')}</td><td>${badge(r.status)}</td><td>${date(r.due_date)}</td><td>${money(r.approximate_price)}</td><td>${money(r.advance_amount)}</td></tr>`);
  }

  function renderPurchases(data) {
    const p = data.purchases || {}, o = p.overview || {};
    $('purchaseHeroValue').textContent = money(o.value,true);
    $('purchaseKpis').innerHTML = [kpi('Bills',number(o.count),'Purchase entries','#7259ff'),kpi('Purchase value',money(o.value,true),'Selected period','#e7529e'),kpi('Paid',money(o.paid,true),'Recorded payment','#1dbb83'),kpi('Outstanding',money(o.outstanding,true),'Supplier exposure','#ef5d67'),kpi('Average bill',money(o.average_bill,true),'Per purchase','#ff914d')].join('');
    lineChart('purchaseTrendChart',rows(p.trend),'period',[dataset('Purchase value','value','#ff914d',true,'y'),dataset('Bills','count','#7259ff',false,'y1')],{dual:true,moneyAxis:true});
    doughnut('purchaseMaterialChart',p.materials,'name','value'); horizontalBar('vendorSpendChart',p.vendors,'name','value',true); doughnut('paymentStatusChart',p.payment_status,'name','count');
    $('recentPurchasesTable').innerHTML = tableRows(p.recent,(r)=>`<tr><td>${date(r.purchase_date)}</td><td>${badge(r.material)}</td><td><strong>${esc(r.vendor||'-')}</strong></td><td>${esc(r.invoice_no||'-')}</td><td>${badge(r.payment_status||'Not recorded')}</td><td>${date(r.due_date)}</td><td>${money(r.value)}</td></tr>`);
  }

  function renderRequirements(data) {
    const r = data.requirements || {}, o = r.overview || {};
    $('requirementHeroValue').textContent = `${number(o.fulfilment_rate)}%`;
    $('requirementKpis').innerHTML = [kpi('Requirements',number(o.total),'Raised','#7259ff'),kpi('Ready',number(o.ready),'Prepared bags','#1dbb83'),kpi('Pending',number(o.pending),'In workflow','#ff914d'),kpi('Overdue',number(o.overdue),'Needs escalation','#ef5d67'),kpi('Turnaround',`${number(o.average_turnaround_hours)} hrs`,'Average readiness','#18b9cc')].join('');
    lineChart('requirementTrendChart',rows(r.trend),'period',[dataset('Raised','raised','#7259ff',true,'y'),dataset('Ready','ready','#1dbb83',true,'y')]); doughnut('requirementStatusChart',r.status,'name','count');
    $('requirementsTable').innerHTML = tableRows(r.recent,(x)=>`<tr><td><strong>${esc(x.requirement_no||'-')}</strong></td><td>${esc(x.order_no||'-')}</td><td>${esc(x.requested_by_name||'-')}</td><td>${esc(x.assigned_to_name||'Unassigned')}</td><td>${date(x.required_by)}</td><td>${badge(x.status)}</td></tr>`);
  }

  function renderInventory(data) {
    const inv = data.inventory || {}; $('inventoryHeroValue').textContent = money(inv.total_value,true);
    const items = ['gold','diamond','stone'].map((key,i)=>({key,row:inv[key]||{},color:palette[i]}));
    items.push({key:'finished goods',row:{quantity:inv.finished_goods?.available||0,unit:'available',value:0,skus:inv.finished_goods?.total||0,negative_skus:0},color:palette[4]});
    $('materialCards').innerHTML = items.map(({key,row,color})=>`<article class="material-card"><div class="material-icon" style="background:${color}18;color:${color}">${key.charAt(0).toUpperCase()}</div><h3>${number(row.quantity)}</h3><p>${esc(row.unit||'')} · ${money(row.value,true)}</p><footer>${number(row.skus)} SKU / items · ${number(row.negative_skus)} negative</footer></article>`).join('');
    doughnut('inventoryValueChart',inv.mix,'name','value');
    const mix=rows(inv.mix); groupedBar('inventorySkuChart',mix.map(x=>x.name),[{label:'Quantity',data:mix.map(x=>number(x.quantity)),backgroundColor:'#7259ff'},{label:'Negative SKU',data:mix.map(x=>number(x.negative_skus)),backgroundColor:'#ef5d67'}]);
  }

  function renderTeam(data) {
    const e=data.employee_performance||{},t=e.totals||{},ranking=rows(e.ranking); $('teamHeroValue').textContent=number(t.average_score);
    $('teamKpis').innerHTML=[kpi('Team members',number(t.staff_count),'Active staff','#7259ff'),kpi('Average score',number(t.average_score),'Performance index','#e7529e'),kpi('On-time rate',`${number(t.on_time_rate)}%`,'Execution discipline','#1dbb83'),kpi('Completed actions',number(t.completed_actions),'This month','#18b9cc'),kpi('Overdue actions',number(t.overdue_actions),'Needs attention','#ef5d67')].join('');
    horizontalBar('employeeScoreChart',ranking,'name','score',false);
    doughnut('teamDisciplineChart',[{name:'On time',count:number(t.completed_actions)-number(t.overdue_actions)},{name:'Overdue',count:number(t.overdue_actions)}],'name','count');
    $('employeeCards').innerHTML=ranking.map((x,index)=>`<article class="employee-card"><div class="employee-head"><div class="employee-avatar">${esc((x.name||'S').charAt(0))}</div><div class="employee-name"><strong>#${index+1} ${esc(x.name||'Staff')}</strong><span>${esc(x.role_label||'Team member')}</span></div><div class="employee-score">${number(x.score)}</div></div><div class="score-bar"><span style="width:${clamp(number(x.score),0,100)}%"></span></div><div class="employee-metrics"><div><strong>${number(x.on_time_rate)}%</strong><span>On time</span></div><div><strong>${number(x.rating_average)}/5</strong><span>Rating</span></div><div><strong>${number(x.points_earned)}</strong><span>Points</span></div><div><strong>${number(x.order_entries)}</strong><span>Orders</span></div><div><strong>${number(x.proper_followups)}</strong><span>Followups</span></div><div><strong>${number(x.purchases)+number(x.issuements)}</strong><span>Material ops</span></div></div></article>`).join('')||empty('No employee activity in this month.');
  }

  function renderRelationships(data) {
    const c=data.customers||{}; $('relationshipHeroValue').textContent=`${number(c.repeat_rate)}%`;
    horizontalBar('customerPortfolioChart',c.top,'name','value',true);
    const kar=rows(data.karigars); groupedBar('karigarChart',kar.map(x=>x.name),[{label:'Orders',data:kar.map(x=>number(x.orders)),backgroundColor:'#7259ff'},{label:'Completed',data:kar.map(x=>number(x.completed)),backgroundColor:'#1dbb83'}],true);
    horizontalBar('relationshipVendorChart',data.purchases?.vendors,'name','value',true);
  }

  function kpi(label,value,note,color){return `<article class="kpi-card" style="--accent:${color}"><label>${esc(label)}</label><strong>${esc(String(value))}</strong><small>${note}</small></article>`}
  function trend(item){const p=number(item?.percent),cls=p>=0?'up':'down';return `<span class="trend ${cls}">${p>=0?'↑':'↓'} ${Math.abs(p)}%</span> vs previous`}
  function dataset(label,key,color,fill=false,axis='y'){return {label,key,borderColor:color,backgroundColor:fill?`${color}20`:color,fill,tension:.38,borderWidth:2.2,pointRadius:2,pointHoverRadius:5,yAxisID:axis}}
  function lineChart(id,data,labelKey,defs,opts={}){const labels=data.map(x=>prettyPeriod(x[labelKey]));const sets=defs.map(d=>({...d,data:data.map(x=>number(x[d.key]))}));chart(id,'line',{labels,datasets:sets},{interaction:{mode:'index',intersect:false},scales:scales(opts),plugins:plugins()})}
  function doughnut(id,data,labelKey,valueKey){data=rows(data);const labels=data.map(x=>title(x[labelKey]));const values=data.map(x=>number(x[valueKey]));const safe=values.some(Boolean)?{labels,values}:{labels:['No data'],values:[1]};chart(id,'doughnut',{labels:safe.labels,datasets:[{data:safe.values,backgroundColor:safe.labels[0]==='No data'?['#ececf3']:palette,borderWidth:0,hoverOffset:7}]},{cutout:'68%',plugins:plugins('bottom')})}
  function bar(id,labels,values,colors,moneyValues=false){chart(id,'bar',{labels,datasets:[{data:values.map(number),backgroundColor:colors,borderRadius:8,borderSkipped:false}]},{plugins:plugins(false),scales:{x:grid(false),y:{...grid(true),ticks:{callback:v=>moneyValues?money(v,true):v}}}})}
  function barRows(id,data,labelKey,valueKey){data=rows(data);bar(id,data.map(x=>title(x[labelKey])),data.map(x=>x[valueKey]),palette)}
  function horizontalBar(id,data,labelKey,valueKey,moneyValues=false){data=rows(data).slice(0,10);chart(id,'bar',{labels:data.map(x=>x[labelKey]||'-'),datasets:[{data:data.map(x=>number(x[valueKey])),backgroundColor:data.map((_,i)=>palette[i%palette.length]),borderRadius:7,borderSkipped:false}]},{indexAxis:'y',plugins:plugins(false),scales:{x:{...grid(true),ticks:{callback:v=>moneyValues?money(v,true):v}},y:grid(false)}})}
  function groupedBar(id,labels,datasets,horizontal=false){chart(id,'bar',{labels,datasets:datasets.map(x=>({...x,borderRadius:6,borderSkipped:false}))},{indexAxis:horizontal?'y':'x',plugins:plugins(),scales:{x:grid(!horizontal),y:grid(horizontal)}})}
  function chart(id,type,data,options){if(state.charts[id])state.charts[id].destroy();const node=$(id);if(!node)return;state.charts[id]=new Chart(node,{type,data,options:{responsive:true,maintainAspectRatio:false,animation:{duration:550},...options}})}
  function plugins(position='top'){return{legend:{display:position!==false,position:position||'top',labels:{usePointStyle:true,boxWidth:7,boxHeight:7,font:{size:9,weight:'600'},padding:15}},tooltip:{backgroundColor:'#171832',titleColor:'#fff',bodyColor:'#ddddec',padding:10,cornerRadius:9,displayColors:true}}}
  function scales(opts={}){const s={x:grid(false),y:{...grid(true),ticks:{callback:v=>opts.moneyAxis?money(v,true):v}}};if(opts.dual)s.y1={position:'right',grid:{display:false},ticks:{callback:v=>opts.moneySecondary?money(v,true):v}};return s}
  function grid(show){return{grid:{display:show,color:'rgba(228,229,239,.65)',drawBorder:false},ticks:{font:{size:9},maxTicksLimit:7}}}

  function switchPage(page){document.querySelectorAll('.nav-link').forEach(x=>x.classList.toggle('active',x.dataset.page===page));document.querySelectorAll('.page').forEach(x=>x.classList.toggle('active',x.dataset.pagePanel===page));$('pageEyebrow').textContent=pageMeta[page][0];$('pageTitle').textContent=pageMeta[page][1];closeMenu();setTimeout(()=>Object.values(state.charts).forEach(x=>x.resize()),20);window.scrollTo({top:0,behavior:'smooth'})}
  function openMenu(){$('sidebar').classList.add('open');$('menuScrim').classList.add('show')} function closeMenu(){$('sidebar').classList.remove('open');$('menuScrim').classList.remove('show')}
  function rows(value){return Array.isArray(value)?value:[]} function number(v){const n=Number(v);return Number.isFinite(n)?Math.round(n*10)/10:0}
  function money(v,compact=false){const n=Number(v)||0;return new Intl.NumberFormat('en-IN',{style:'currency',currency:'INR',maximumFractionDigits:compact?1:0,notation:compact?'compact':'standard'}).format(n)}
  function date(v){if(!v)return'-';const d=new Date(String(v).length===10?`${v}T00:00:00`:v);return isNaN(d)?'-':new Intl.DateTimeFormat('en-IN',{day:'2-digit',month:'short',year:'numeric'}).format(d)}
  function dateTime(v){if(!v)return'-';const d=new Date(v);return isNaN(d)?'-':new Intl.DateTimeFormat('en-IN',{day:'2-digit',month:'short',hour:'2-digit',minute:'2-digit'}).format(d)}
  function prettyPeriod(v){if(!v)return'-';if(/^\d{4}-\d{2}$/.test(v)){const [y,m]=v.split('-');return new Date(+y,+m-1,1).toLocaleDateString('en-IN',{month:'short',year:'2-digit'})}return new Date(`${v}T00:00:00`).toLocaleDateString('en-IN',{day:'2-digit',month:'short'})}
  function title(v){return String(v||'-').replace(/_/g,' ').replace(/\b\w/g,m=>m.toUpperCase())} function clamp(n,a,b){return Math.min(b,Math.max(a,n))}
  function esc(v){return String(v??'').replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]))}
  function statusClass(v){v=String(v||'').toLowerCase();if(/complete|ready|paid|received|approved/.test(v))return'green';if(/overdue|reject|cancel|unpaid/.test(v))return'red';if(/pending|due|partial|progress/.test(v))return'orange';if(/gold|diamond|stone/.test(v))return'purple';return'grey'}
  function badge(v){return `<span class="badge ${statusClass(v)}">${esc(title(v||'-'))}</span>`} function empty(message){return `<p style="color:#8d8fa3;font-size:11px">${esc(message)}</p>`}
  function tableRows(data,render){data=rows(data);return data.length?data.map(render).join(''):`<tr><td colspan="9" style="text-align:center;color:#989aab;padding:28px">No data in this period</td></tr>`}

  $('loginForm').addEventListener('submit',login); $('logoutButton').addEventListener('click',()=>logout()); $('retryButton').addEventListener('click',loadAnalytics); $('refreshButton').addEventListener('click',loadAnalytics); $('periodSelect').addEventListener('change',loadAnalytics);
  document.querySelectorAll('.nav-link').forEach(x=>x.addEventListener('click',()=>switchPage(x.dataset.page))); $('openMenu').addEventListener('click',openMenu); $('closeMenu').addEventListener('click',closeMenu); $('menuScrim').addEventListener('click',closeMenu);
  $('profileButton').addEventListener('click',()=> $('profileMenu').classList.toggle('hidden')); document.addEventListener('click',(e)=>{if(!e.target.closest('.profile-button')&&!e.target.closest('.profile-menu'))$('profileMenu').classList.add('hidden')});
  window.addEventListener('beforeinstallprompt',(e)=>{e.preventDefault();state.deferredInstall=e;$('installButton').classList.remove('hidden')});
  $('installButton').addEventListener('click',async()=>{if(!state.deferredInstall)return;state.deferredInstall.prompt();await state.deferredInstall.userChoice;state.deferredInstall=null;$('installButton').classList.add('hidden')});
  if('serviceWorker'in navigator)window.addEventListener('load',()=>navigator.serviceWorker.register('sw.js').catch(()=>{}));
  restore();
})();
