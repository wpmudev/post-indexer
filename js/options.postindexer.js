function pishowTooltip(x, y, contents) {
   jQuery('<div id="pitooltip">' + contents + '</div>').css( {
		position: 'absolute',
        display: 'none',
        top: y,
        left: x,
        border: '2px solid #333',
        padding: '10px',
        'background-color': '#EEE',
        opacity: 0.80
   }).appendTo("body").fadeIn(200);
}

function piSetWidth() {
	var width = jQuery('#post-type-stats-chart').parents('div.inside').width();
	jQuery('#post-type-stats-chart').width((width - 20) + 'px').height( Math.round(width / 2) + 'px');

	var width = jQuery('#blog-stats-chart').parents('div.inside').width();
	jQuery('#blog-stats-chart').width((width - 20) + 'px');
}

function piBuildPostChart(chart) {

		var pie_options = {
          series: {
            pie: {
              show: true,
              radius: 1,
              label: {
                  show: true,
                  radius: 3/4,
                  formatter: function(label, series){
                      return '<div style="font-size:8pt;font-weight:bold;text-align:center;padding:2px;color:white;">'+Math.round(series.percent)+'%</div>';
                  },
                  background: { opacity: 0.5 }
              }
            }
          },
          legend: { show: true, backgroundOpacity: 0.5 }
        };

	piposttypeplot = jQuery.plot(jQuery('#post-type-stats-chart'), chart, pie_options);

}

function piBuildBlogChart(chart) {

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

	piblogplot = jQuery.plot(jQuery('#blog-stats-chart'), blogcountdata, options);

	var previousPoint = null;
	jQuery("#blog-stats-chart").bind("plothover", function (event, pos, item) {
	    if (item) {
	    	if (previousPoint != item.datapoint) {
	        	previousPoint = item.datapoint;

	            jQuery("#pitooltip").remove();
	            var x = item.datapoint[0].toFixed(0),
	            	y = item.datapoint[1].toFixed(0) - item.datapoint[2].toFixed(0);

	                pishowTooltip(item.pageX, item.pageY,
	                            y + ' ' + item.series.label);
	        }
		} else {
	    	jQuery("#pitooltip").remove();
			previousPoint = null;
		}
	});

}

function piPlotCharts() {

	piBuildPostChart( posttypedata.chart );
	piBuildBlogChart( blogcountdata.chart );

}

function piStatisticsReady() {

	chart = false;
	ticks = false;

	piSetWidth();
	piPlotCharts();

	jQuery(window).resize( function() {
		piSetWidth();
		piPlotCharts();
	});



}


jQuery(document).ready(piStatisticsReady);