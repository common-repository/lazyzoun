if (!typeof jQuery == 'undefined') {
	// jQuery is not loaded  - no hover effect
	jQuery(document).ready(function() {	
		jQuery('.fade_hover').hover(
			function() {
					jQuery(this).stop().animate({opacity:0.4},400);
				},
				function() {
					jQuery(this).stop().animate({opacity:1},400);
			});	
	}); 
}