$(function () {

    $(".button").click(function () {

        href = location.protocol+"//"+location.hostname+"/"+location.pathname + "?action=updatetokens&access=" + $("#access-token").val() + "&refresh=" + $("#refresh-token").val()
        console.log(href)
        fetch(href)
       // location.reload();
    })

})