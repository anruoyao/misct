$(document).ready(function () {
  $(".sideBarli").removeClass("activeLi");
  $(".indexSideA").addClass("activeLi");

  var d = new Date(),
    month = String(d.getMonth() + 1).padStart(2, "0"),
    year = d.getFullYear();

  $("#months").val(month);
  $("#years").val(year);

  let chart;

  function fetchAllData(month, year) {
    $.ajax({
      url: domainUrl + "fetchAllChartData",
      type: "GET",
      data: { month: month, year: year },
      success: function (response) {
        if (
          !response ||
          !response.users ||
          !response.posts ||
          !response.reels ||
          !response.rooms
        ) {
          console.error("Invalid response format", response);
          return;
        }

        const dates = response.users.map((item) => item.date);

        const series = [
          { name: "Users", data: response.users.map((item) => item.count) },
          { name: "Posts", data: response.posts.map((item) => item.count) },
          { name: "Reels", data: response.reels.map((item) => item.count) },
          { name: "Rooms", data: response.rooms.map((item) => item.count) },
        ];

        if (chart) {
          chart.updateOptions({ xaxis: { categories: dates } });
          chart.updateSeries(series);
        } else {
          chart = new ApexCharts(document.querySelector("#allInOneChart"), {
            chart: { type: "area", height: 400, stacked: false },
            stroke: { width: 2, curve: "smooth" },
            colors: ["#3ece70", "#007bff", "#ff9800", "#9c27b0"],
            dataLabels: { enabled: false },
            series: series,
            markers: { size: 0 },
            xaxis: { type: "category", categories: dates },
            tooltip: { x: { format: "dd MMM yyyy" } },
            fill: {
              type: "gradient",
              gradient: {
                shadeIntensity: 1,
                opacityFrom: 0.7,
                opacityTo: 0,
                stops: [0, 100],
              },
            },
          });
          chart.render();
        }
      },
      error: function (xhr) {
        console.error("Ajax error:", xhr.responseText);
      },
    });
  }

  fetchAllData(month, year);

  $("#months, #years").on("change", function () {
    month = $("#months").val();
    year = $("#years").val();
    fetchAllData(month, year);
  });
});
