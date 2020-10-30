$(function(){
    $('#videoFile').change(ev=> {
        $(ev.target).closest('form').trigger('submit');
    })
});