<?php
namespace App\Models;
class Mother {
  const STAGES = ['New','Incomplete','Pre Order','Assign','Pending','AI Order','Scheduled','WFP','Approved','Packaging','Shipment','Partial Delivered','Delivered','Late Delivery','Partial Return Pending','Return Pending','Partial Returned','Returned','Exchange Received','Cancel'];
  const RED = ['Late Delivery','Return Pending','Partial Return Pending'];
}
