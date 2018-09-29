
/**
 * First we will load all of this project's JavaScript dependencies which
 * includes Vue and other libraries. It is a great starting point when
 * building robust, powerful web applications using Vue and Laravel.
 */

require('./bootstrap');

$(function(){
	$('[data-save]').on('click', function() {
		let url = $(this).data('save');
		let registred = [];
		var button = $(this);

		$('input[type=checkbox]:checked').each(function() {
			registred.push($(this).attr('data-id'))
		})

		console.log(registred);
		
		button.prop('disabled', true);

		axios.post(url, {
			'participants': registred
		})
		.then(function (response) {
			button.prop('disabled', false);
			console.log(response.data);
		})
		.catch(function (error) {
			console.log(error);
		});
	})

	$('.events-list .event').on('click', function() {
		var link = $(this).find('a');
		if (link.length) {
			document.location.href = link.attr('href');
		}
	})

	$('.dates select').on('change', function() {
		var value = $(this).val();
		document.location.href = '/' + value;
	})
});