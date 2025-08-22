function viewPage(evt, pageName) {
    
    var i, tabcontent, tablinks;

    tabcontent = document.getElementsByClassName("page");
    for (i = 0; i < tabcontent.length; i++) {
        tabcontent[i].style.display = "none";
    }

    tablinks = document.getElementsByClassName("list-item");
    for (i = 0; i < tablinks.length; i++) {
        tablinks[i].className = tablinks[i].className.replace(" active", "");
    }

    document.getElementById(pageName).style.display = "block";
    evt.currentTarget.className += " active";
}

document.getElementById("defaultOpen").click();