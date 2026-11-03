<?php
namespace App\Models;
class Mother {
  const STAGES = ['New','Incomplete','Pre Order','Assign','Pending','AI Order','Scheduled','WFP','Approved','Packaging','Shipment','Partial Delivered','Delivered','Late Delivery','Return Pending','Returned','Exchange Received','Cancel'];
  /** Old return statuses, retired. Orders that had them were moved to Return Pending / Returned (the kind of return stays in orders.return_type). */
  const LEGACY = ['Partial Return Pending','Partial Return Received','Partial Returned','Exchange Pending'];
  const RED = ['Late Delivery','Return Pending'];
}
