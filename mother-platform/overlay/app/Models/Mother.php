<?php
namespace App\Models;
class Mother {
  const STAGES = ['New','Incomplete','Pre Order','Assign','Pending','AI Order','Approved','Scheduled','WFP','Packaging','Shipment','Partial Delivered','Delivered','Late Delivery','Return Pending','Returned','Exchange Received','Cancel'];
  const RED = ['Late Delivery','Return Pending'];
}
