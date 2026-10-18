(() => {
  'use strict';
  if (!document.querySelector('[data-admin-dashboard]')) return;
  const $ = selector => document.querySelector(selector);
  const $$ = selector => [...document.querySelectorAll(selector)];
  const periods = JSON.parse($('#admin-attendance-data').textContent);
  const state = {period: 'day', type: 'line', hidden: [false, false]};
  const number = value => new Intl.NumberFormat('id-ID').format(value);
  const percent = value => new Intl.NumberFormat('id-ID', {minimumFractionDigits: 2, maximumFractionDigits: 2}).format(value);
  const reducedMotion = matchMedia('(prefers-reduced-motion: reduce)').matches;
  let chart;
  const secondaryCharts = [];

  function render() {
    const period = periods[state.period];
    const totals = period.summary;
    $('#summary-title').textContent = period.title;
    const rate = $('#attendance-rate');
    rate.textContent = totals.opportunities ? percent(totals.present / totals.opportunities * 100) : '-';
    if (totals.opportunities) {const sign = document.createElement('span'); sign.textContent = '%'; rate.append(sign);}
    $('#summary-basis').textContent = !totals.opportunities ? 'Tidak ada kewajiban presensi'
      : state.period === 'day' ? `${number(totals.present)} dari ${number(totals.opportunities)} siswa` : `${number(totals.present)} kehadiran / ${totals.days} hari`;
    const colors = {present: '#0f766e', permit: '#5a91ba', sick: '#e5be40', absent: '#c25d64', pending: '#aab6c2'};
    for (const key of [...Object.keys(colors), 'excluded', 'late']) $(`#count-${key}`).textContent = number(totals[key]);
    $('#excluded-row').hidden = totals.excluded === 0;
    $('#attendance-track').replaceChildren(...Object.keys(colors).map(key => {
      const segment = document.createElement('span');
      segment.style.width = `${totals.opportunities ? totals[key] / totals.opportunities * 100 : 0}%`;
      segment.style.backgroundColor = colors[key];
      return segment;
    }));
    $('#chart-subtitle').textContent = period.subtitle;
    $('#chart-date').textContent = period.date;
    $('#chart-unit').textContent = state.period === 'day' ? 'Jumlah siswa' : 'Siswa per hari';
    $('#chart-empty').hidden = !period.empty;
    $('#chart-empty p').textContent = period.empty || '';
    $('#chart-untimed').hidden = !totals.untimed || state.period !== 'day';
    $('#chart-untimed').textContent = `${number(totals.untimed)} siswa hadir tanpa jam masuk yang valid pada grafik.`;
    const description = `${period.subtitle}, ${period.date}. ${number(totals.present)} kehadiran, termasuk ${number(totals.late)} terlambat. ${period.empty || ''}`;
    $('#chart-description').textContent = description;
    $('#attendance-chart').setAttribute('aria-label', description);
    $('#attendance-chart').setAttribute('aria-describedby', 'chart-description');
    $('#attendance-detail').href = $('#attendance-detail').dataset[`${state.period}Url`];
    $$('[data-period]').forEach(button => button.setAttribute('aria-pressed', String(button.dataset.period === state.period)));
    $$('[data-chart-type]').forEach(button => button.setAttribute('aria-pressed', String(button.dataset.chartType === state.type)));
    $$('[data-series]').forEach(button => button.setAttribute('aria-pressed', String(!state.hidden[Number(button.dataset.series)])));
    $('#dashboard-status').textContent = `${period.title}. Grafik ${state.type === 'bar' ? 'batang' : 'garis'}.`;
    if (!window.Chart) {$('#chart-empty').hidden = false; $('#chart-empty p').textContent = 'Grafik tidak berhasil dimuat. Data tersedia melalui tombol tabel.'; return;}
    const data = {labels: period.points.map(point => point.date), datasets: [
      {label: 'Hadir', data: period.points.map(point => point.present), borderColor: '#0f766e', backgroundColor: state.type === 'bar' ? '#16847b' : '#0f766e0b', fill: state.type === 'line', borderWidth: state.type === 'line' ? 2.5 : 0, cubicInterpolationMode: 'monotone', pointRadius: 3, pointHoverRadius: 5, pointBackgroundColor: '#fff', pointBorderWidth: 2, borderRadius: 4, maxBarThickness: 26, hidden: state.hidden[0], spanGaps: false},
      {label: 'Terlambat', data: period.points.map(point => point.late), borderColor: '#c5a01b', backgroundColor: '#e5c349', fill: false, borderWidth: state.type === 'line' ? 2 : 0, cubicInterpolationMode: 'monotone', pointRadius: 2.5, pointHoverRadius: 5, borderDash: state.type === 'line' ? [4,3] : [], borderRadius: 4, maxBarThickness: 26, hidden: state.hidden[1], spanGaps: false},
    ]};
    const max = Math.max(1, ...period.points.map(point => point.present || 0));
    const maximum = max > 100 ? Math.ceil(max / 100) * 100 : max > 10 ? Math.ceil(max / 10) * 10 : 5;
    if (chart) {
      chart.config.type = state.type; chart.data = data;
      chart.options.scales.x.title.text = period.axis; chart.options.scales.x.offset = state.type === 'bar';
      chart.options.scales.y.suggestedMax = maximum;
      chart.options.animation.duration = reducedMotion ? 0 : 280;
      chart.update();
    } else {
      Chart.defaults.font.family = '"Segoe UI", Arial, sans-serif';
      Chart.defaults.color = '#4d6476';
      Chart.defaults.font.size = 13;
      chart = new Chart($('#attendance-chart'), {type: state.type, data, options: {
        responsive: true, maintainAspectRatio: false, animation: {duration: 0},
        interaction: {mode: 'index', intersect: false}, layout: {padding: {top:8,right:8}},
        datasets: {bar: {categoryPercentage: .65, barPercentage: .78}},
        plugins: {legend: {display:false}, tooltip: {backgroundColor:'#153d5f',titleColor:'#fff',bodyColor:'#f4f8fc',padding:13,cornerRadius:6,boxPadding:5,usePointStyle:true,titleFont:{size:14},bodyFont:{size:13},callbacks:{
          title(items) {const point=periods[state.period].points[items[0].dataIndex];return point.full || `${point.date} WIB`;},
          label(context) {return ` ${context.dataset.label}: ${number(context.parsed.y)} siswa`;},
          afterBody: () => ['Terlambat termasuk dalam jumlah hadir'],
        }}},
        scales: {x: {grid:{display:false},border:{display:false},ticks:{maxRotation:0,autoSkip:true,maxTicksLimit:innerWidth<=560?4:7,padding:9,font:{size:13}},title:{display:true,text:period.axis,font:{size:13},padding:{top:5}}},
          y:{beginAtZero:true,suggestedMax:maximum,grid:{color:'#edf2f5',drawTicks:false},border:{display:false},ticks:{precision:0,maxTicksLimit:6,padding:10,font:{size:13}}}},
      }});
    }
  }
  $$('[data-period]').forEach(button => button.addEventListener('click', () => {state.period=button.dataset.period;state.type=periods[state.period].type;render();}));
  $$('[data-chart-type]').forEach(button => button.addEventListener('click', () => {state.type=button.dataset.chartType;render();}));
  $$('[data-series]').forEach(button => button.addEventListener('click', () => {const index=Number(button.dataset.series);if(!state.hidden[index] && state.hidden[1-index])return;state.hidden[index]=!state.hidden[index];render();}));

  const dialog = $('#admin-chart-dialog');
  let dialogTrigger;
  $('#chart-table').addEventListener('click', () => {
    dialogTrigger=$('#chart-table');
    $('#admin-chart-dialog-category').textContent='PRESENSI SISWA';
    $('#admin-chart-dialog-title').textContent='Data statistik kehadiran';
    const period=periods[state.period];const content=$('#admin-chart-content');content.replaceChildren();
    const note=document.createElement('p');note.className='dialog-note';note.textContent=`${period.subtitle} / ${period.date}. Terlambat termasuk dalam jumlah hadir.`;content.append(note);
    const scroll=document.createElement('div');scroll.className='table-scroll';const table=document.createElement('table');table.className='data-table';
    const head=table.createTHead().insertRow();for(const label of [state.period==='day'?'Jam (WIB)':'Tanggal','Hadir','Terlambat']){const cell=document.createElement('th');cell.scope='col';cell.textContent=label;head.append(cell);}
    const body=table.createTBody();for(const point of period.points){const row=body.insertRow();[point.date,point.present,point.late].forEach((value,index)=>{const cell=row.insertCell();cell.textContent=value===null?'-':index?number(value):value;if(index)cell.className='number';});}
    if (!period.points.length) {const cell=body.insertRow().insertCell();cell.colSpan=3;cell.textContent=period.empty || 'Belum ada data.';}
    scroll.append(table);content.append(scroll);hideTooltip();dialog.showModal();
  });
  $('#admin-chart-close').addEventListener('click',()=>dialog.close());
  dialog.addEventListener('close',()=>dialogTrigger?.focus({preventScroll:true}));
  dialog.addEventListener('click',event=>{const box=dialog.getBoundingClientRect();if(event.target===dialog && (event.clientX<box.left||event.clientX>box.right||event.clientY<box.top||event.clientY>box.bottom))dialog.close();});

  function secondaryChart(root) {
    const find=selector=>root.querySelector(selector), all=selector=>[...root.querySelectorAll(selector)];
    const config=JSON.parse(find('[data-stat-data]').textContent);
    const current={period:'day',type:config.periods.day.type,hidden:config.series.map(()=>false)};
    let instance;
    function update() {
      const p=config.periods[current.period];
      find('[data-stat-caption]').textContent=p.subtitle;
      find('[data-stat-date]').textContent=p.date;
      find('[data-stat-empty]').hidden=!p.empty;
      find('[data-stat-empty] p').textContent=p.empty || '';
      find('[data-stat-note]').textContent=p.note;
      find('[data-stat-untimed]').hidden=!p.untimed;
      find('[data-stat-untimed]').textContent=p.untimed || '';
      all('[data-stat-count]').forEach(e=>e.textContent=number(p.summary[e.dataset.statCount]));
      all('[data-stat-period]').forEach(e=>e.setAttribute('aria-pressed',String(e.dataset.statPeriod===current.period)));
      all('[data-stat-type]').forEach(e=>e.setAttribute('aria-pressed',String(e.dataset.statType===current.type)));
      all('[data-stat-series]').forEach(e=>e.setAttribute('aria-pressed',String(!current.hidden[Number(e.dataset.statSeries)])));
      const description=`${p.subtitle}, ${p.date}. ${Object.entries(config.metrics).map(([key,label])=>`${label}: ${number(p.summary[key])}`).join(', ')}. ${p.empty || ''}`;
      find('[data-stat-description]').textContent=description;
      find('canvas').setAttribute('aria-label',description);
      if(!window.Chart){find('[data-stat-empty]').hidden=false;find('[data-stat-empty] p').textContent='Grafik tidak berhasil dimuat. Gunakan tombol tabel untuk melihat data.';return;}
      const data={labels:p.points.map(point=>point.date),datasets:config.series.map((s,index)=>({label:s.label,data:p.points.map(point=>point[s.key]),borderColor:s.color,backgroundColor:current.type==='bar'?s.color:`${s.color}0b`,
        fill:current.type==='line'&&index===0,borderWidth:current.type==='line'?2:0,pointRadius:2.5,pointHoverRadius:5,pointBackgroundColor:'#fff',pointBorderWidth:2,
        cubicInterpolationMode:'monotone',borderRadius:3,maxBarThickness:22,spanGaps:false,hidden:current.hidden[index]}))};
      if(instance){instance.config.type=current.type;instance.data=data;instance.options.scales.x.title.text=p.axis;instance.options.scales.x.offset=current.type==='bar';instance.update();return;}
      instance=new Chart(find('canvas'),{type:current.type,data,options:{responsive:true,maintainAspectRatio:false,animation:{duration:reducedMotion?0:220},
        interaction:{mode:'index',intersect:false},plugins:{legend:{display:false},tooltip:{backgroundColor:'#153d5f',padding:12,cornerRadius:6,titleFont:{size:14},bodyFont:{size:13},callbacks:{
          title(items){const point=config.periods[current.period].points[items[0].dataIndex];return point.full || `${point.date} WIB`;},
          label(ctx){return ` ${ctx.dataset.label}: ${number(ctx.parsed.y)}`;},
        }}},scales:{x:{offset:current.type==='bar',grid:{display:false},border:{display:false},ticks:{maxRotation:0,maxTicksLimit:innerWidth<=560?4:7},title:{display:true,text:p.axis,font:{size:13}}},
          y:{beginAtZero:true,suggestedMax:5,grid:{color:'#edf2f5'},border:{display:false},ticks:{precision:0,maxTicksLimit:6}}}}});
      secondaryCharts.push(instance);
    }
    all('[data-stat-period]').forEach(e=>e.addEventListener('click',()=>{current.period=e.dataset.statPeriod;current.type=config.periods[current.period].type;update();$('#dashboard-status').textContent=`${root.querySelector('h2').textContent}, ${e.textContent}.`;}));
    all('[data-stat-type]').forEach(e=>e.addEventListener('click',()=>{current.type=e.dataset.statType;update();}));
    all('[data-stat-series]').forEach(e=>e.addEventListener('click',()=>{const index=Number(e.dataset.statSeries);if(!current.hidden[index]&&current.hidden.filter(v=>!v).length===1)return;current.hidden[index]=!current.hidden[index];update();}));
    find('[data-stat-table]').addEventListener('click',()=>{
      dialogTrigger=find('[data-stat-table]');
      const p=config.periods[current.period],content=$('#admin-chart-content');content.replaceChildren();
      $('#admin-chart-dialog-category').textContent=find('.section-kicker').textContent;
      $('#admin-chart-dialog-title').textContent=`Data ${find('h2').textContent.toLowerCase()}`;
      const note=document.createElement('p');note.className='dialog-note';note.textContent=`${p.subtitle} / ${p.date}. ${p.note} ${p.untimed || ''}`;content.append(note);
      const scroll=document.createElement('div');scroll.className='table-scroll';const table=document.createElement('table');table.className='data-table';
      const head=table.createTHead().insertRow();for(const label of [current.period==='day'?'Jam (WIB)':'Tanggal',...config.series.map(s=>s.label)]){const th=document.createElement('th');th.scope='col';th.textContent=label;head.append(th);}
      const body=table.createTBody();for(const point of p.points){const row=body.insertRow();[point.full || point.date,...config.series.map(s=>point[s.key])].forEach((value,index)=>{const cell=row.insertCell();cell.textContent=value===null?'-':index?number(value):value;if(index)cell.className='number';});}
      if(!p.points.length){const cell=body.insertRow().insertCell();cell.colSpan=config.series.length+1;cell.textContent=p.empty || 'Belum ada data.';}
      scroll.append(table);content.append(scroll);hideTooltip();dialog.showModal();
    });
    update();
  }

  window.addEventListener('nusa:admin-shell-resize',()=>{
    if(chart){chart.options.scales.x.ticks.maxTicksLimit=innerWidth<=560?4:7;chart.resize();}
    secondaryCharts.forEach(item=>{item.options.scales.x.ticks.maxTicksLimit=innerWidth<=560?4:7;item.resize();});
  });
  function hideTooltip(){document.dispatchEvent(new Event('nusa:tooltip-hide'));}
  render();$$('[data-stat-chart]').forEach(secondaryChart);
})();
