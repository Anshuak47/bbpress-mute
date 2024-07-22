jQuery(document).ready(function(jQuery) {
    jQuery('.bbpm-ban-user').click(function() {

        var profileID = jQuery(this).data('profile_id');
        var loggedinID = jQuery(this).data('user_id');

        Swal.fire({
          title: "Do you want to ban this user?",
          showDenyButton: true,
          showCancelButton: true,
          confirmButtonText: "Yes",
          denyButtonText: "No"
        }).then((result) => {
          /* Read more about isConfirmed, isDenied below */
          if (result.isConfirmed) {

            
                var data = {
                    action: 'bbpm_ban_user',
                    profileID : profileID,
                    loggedinID : loggedinID,
                    nonce: ajax_object.nonce
                };

                jQuery.ajax({
                    url: ajax_object.ajax_url,
                    type: 'POST',
                    data: data,
                    success: function(response) {
                        var jsonData = jQuery.parseJSON(response);

                        if ( jsonData.message == 'success' ) {

                            Swal.fire("This user is banned. Page will refresh now", "", "success");
                             setTimeout(() => 
                                { 
                                    window.location.reload(); 

                                }, 3000 );
                            
                        } else if( jsonData.message == 'error' ) {

                            Swal.fire("Changes are not saved", "", "info");

                        }
                    }
                });
          } else if (result.isDenied) {

                Swal.fire("User not banned", "", "info");

            }
        });

       

    });

    jQuery('.bbpm-unban-user').click(function(e) {
       
        var profileID = jQuery(this).data('profile_id');
        var loggedinID = jQuery(this).data('user_id');

        Swal.fire({
          title: "Unban this user?",
          showDenyButton: true,
          showCancelButton: true,
          confirmButtonText: "Yes",
          denyButtonText: "No"
        }).then((result) => {
          /* Read more about isConfirmed, isDenied below */
          if (result.isConfirmed) {

            
                var data = {
                    action: 'bbpm_unban_user',
                    profileID : profileID,
                    loggedinID : loggedinID,
                    nonce: ajax_object.nonce
                };

                jQuery.ajax({
                    url: ajax_object.ajax_url,
                    type: 'POST',
                    data: data,
                    success: function(response) {
                        var jsonData = jQuery.parseJSON(response);

                        if ( jsonData.message == 'success' ) {

                            Swal.fire("This user is unbanned. This user is banned. Page will refresh now", "", "success");
                            setTimeout(() => 
                                { 
                                    window.location.reload(); 

                                }, 3000 );

                            
                        } else if( jsonData.message == 'error' ) {

                            Swal.fire("Changes are not saved", "", "info");

                        }
                    }
                });
          } else if (result.isDenied) {

                Swal.fire("User not banned", "", "info");

            }
        });

    });
});