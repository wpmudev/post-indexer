function pisiteshowTooltip(x, y, contents) {
   jQuery('<div id="pitooltip">' + contents + '</div>').css( {
		position: 'absolute',
        display: 'none',
        top: y,
        left: x,
        border: '2px solid #333',
        padding: '10px',
        'background-color': '#EEE',
        opacity: 0.80,
		'z-index': 9999
   }).appendTo("body").fadeIn(200);
}

function piBuildSiteStatsChart() {

	var options = {
		series: {
			stack: true,
	    	bars: { show: true, barWidth: 1.0, align: "center" },
	    	points: { show: false },
			lines: {show: false},
		},
		grid: { hoverable: true, backgroundColor: { colors: ["#fff", "#eee"] } },
		xaxis: { tickDecimals: 0, ticks: blogcountinfo.ticks},
		yaxis: { tickDecimals: 0},
		legend: {
		    show: true,
		    position: "ne" },

	  };

	piblogplot = jQuery.plot(jQuery('#singlesitestats'), blogcountdata, options);

	var previousPoint = null;
	jQuery("#singlesitestats").bind("plothover", function (event, pos, item) {
	    if (item) {
	    	if (previousPoint != item.datapoint) {
	        	previousPoint = item.datapoint;

	            jQuery("#pitooltip").remove();
	            var x = item.datapoint[0].toFixed(0),
	            	y = item.datapoint[1].toFixed(0) - item.datapoint[2].toFixed(0);

	                pisiteshowTooltip(item.pageX, item.pageY,
	                            y + ' ' + item.series.label);
	        }
		} else {
	    	jQuery("#pitooltip").remove();
			previousPoint = null;
		}
	});

}

function piPlotSiteChart() {

	piBuildSiteStatsChart();

}

function piSiteStatisticsReady() {

	chart = false;
	ticks = false;

	piPlotSiteChart();

	jQuery(window).resize( function() {
		piPlotSiteChart();
	});



}


jQuery(document).ready(piSiteStatisticsReady);