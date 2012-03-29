$(document).ready(function(){
	$('.spam').click(
		function(){
			if(-1===this.href.indexOf('#')){
				this.href = '#';
				var id=parseInt(this.id.substring(4), 10);
				var f = document.forms['commForm'];
				var r=f['event'].value;
				var i=r.indexOf('#');
				var h=jQuery(this).parent().parent();
				jQuery.post(f['action'], {event : r.substring(0, i) + '#spam', id: id}, function(r){$(h).prepend(commMsgSpam+' ');})
			}
			return false
		}
	);
	$('.delete').click(function(){return confirm(commMsgDelete)})
	$('#commCheckAll').show();
	$('#commCheckAll').click(function(){
		$('#commListForm INPUT[name="it[]"][type="checkbox"]').attr("checked",commCheckAll);
		commCheckAll = commCheckAll ? false : true;
		return false;
	})

})