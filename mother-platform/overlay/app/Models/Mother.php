<?php
namespace App\Models;
class Mother {
  const STAGES = ['New','Incomplete','Pre Order','Assign','Pending','AI Order','Approved','Scheduled','WFP','Packaging','Shipment','Partial Delivered','Delivered','Late Delivery','Return Pending','Returned','Cancel'];
  /** Old return statuses, retired. Orders that had them were moved to Return Pending / Returned (the kind of return stays in orders.return_type). */
  const LEGACY = ['Partial Return Pending','Partial Return Received','Partial Returned','Exchange Pending','Exchange Received'];
  const RED = ['Late Delivery','Return Pending'];
}
