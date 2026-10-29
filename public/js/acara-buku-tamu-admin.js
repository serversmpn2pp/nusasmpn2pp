(() => {
    'use strict';
    if(!window.Chart)return;
    const el=document.getElementById('event-statistics');if(!el)return;
    const data=JSON.parse(el.textContent);
    Chart.defaults.font.family='"Instrument Sans","Segoe UI",Arial,sans-serif';Chart.defaults.color='#526c80';
    function chart(id,type,labels,values,colors,horizontal=false){
        new Chart(document.getElementById(id),{type,data:{labels,datasets:[{label:'Pengunjung',data:values,backgroundColor:colors,borderColor:typeof colors==='string'?colors:'#0f766e',borderWidth:type==='line'?3:0,pointRadius:type==='line'?3:0,tension:.2,maxBarThickness:36,borderRadius:3}]},options:{responsive:true,maintainAspectRatio:false,indexAxis:horizontal?'y':'x',plugins:{legend:{display:false}},scales:{x:{grid:{display:false},ticks:{precision:0,maxTicksLimit:8,maxRotation:0},beginAtZero:true},y:{beginAtZero:true,ticks:{precision:0},grid:{color:'#e6edf3'}}}}});
    }
    chart('event-daily','line',Object.keys(data.harian).map(d=>new Date(d+'T00:00:00').toLocaleDateString('id-ID',{day:'numeric',month:'short'})),Object.values(data.harian),'#0f766e');
    chart('event-hourly','bar',Array.from({length:24},(_,i)=>String(i).padStart(2,'0')+'.00'),data.jam,'#15477a');
    chart('event-satisfaction','bar',['Sangat puas','Puas','Netral','Kurang puas','Tidak puas'],[5,4,3,2,1].map(n=>data.kepuasan[n]),['#0f766e','#39988c','#d2af23','#c78169','#b35067'],true);
    chart('event-categories','bar',Object.values(data.label_kategori),Object.keys(data.label_kategori).map(k=>data.kategori[k]),['#15477a','#0f766e','#d2af23','#b35067','#4b889a']);
})();
