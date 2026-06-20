<?php
$drivers = \App\Models\Driver::whereIn('id', [122, 129, 322])->get(); 
foreach($drivers as $i => $d) { 
    $d->is_online = true; 
    $d->availability_status = 'available'; 
    $d->current_latitude = -1.9536 + ($i * 0.001); 
    $d->current_longitude = 30.1055 + ($i * 0.001); 
    $d->status = 'approved'; 
    $d->save(); 
}
echo "Drivers set online successfully!\n";
