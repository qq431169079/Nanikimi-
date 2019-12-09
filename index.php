<?php

$paginaname = 'Dashboard';

?>
<!DOCTYPE html>
<!--[if IE 9]>         <html class="no-js lt-ie10"> <![endif]-->
<!--[if gt IE 9]><!--> <html class="no-js "> <!--<![endif]-->
			<?php include("@/header.php"); ?>

                    <div id="page-content" style="background: url(bg.jpg)">
                     
                        <div class="row">
                      
                            <div class="col-sm-6 col-lg-3">
                                <a href="javascript:void(0)" class="widget">
                                    <div class="widget-content widget-content-mini text-right clearfix">
                                        <div class="widget-icon pull-left themed-background-danger">
                                            <i class="fa fa-signal text-light-op"></i>
                                        </div>
                                        <h2 class="widget-heading h3 text-danger">
                                            <strong><span data-toggle="counter" data-to="<?php echo $stats -> totalBoots($odb); ?>"></span></strong>
                                        </h2>
                                        <span class="text-muted">การโจมตีทั้งหมด</span>
                                    </div>
                                </a>
                            </div>
                            <div class="col-sm-6 col-lg-3">
                                <a href="javascript:void(0)" class="widget">
                                    <div class="widget-content widget-content-mini text-right clearfix">
                                        <div class="widget-icon pull-left themed-background-info">
                                            <i class="fa fa-cog fa-spin text-light-op"></i>
                                        </div>
                                        <h2 class="widget-heading h3 text-info">
                                            <strong><span data-toggle="counter" data-to="<?php echo $stats -> runningBoots($odb); ?>"></span></strong>
                                        </h2>
                                        <span class="text-muted">การโจมตีตอนนี้</span>
                                    </div>
                                </a>
                            </div>
                            <div class="col-sm-6 col-lg-3">
                                <a href="javascript:void(0)" class="widget">
                                    <div class="widget-content widget-content-mini text-right clearfix">
                                        <div class="widget-icon pull-left themed-background">
                                            <i class="fa fa-sitemap text-light-op"></i>
                                        </div>
                                        <h2 class="widget-heading h3">
                                            <strong><span data-toggle="counter" data-to="<?php echo $stats -> serversonline($odb); ?>"></span></strong>
                                        </h2>
                                        <span class="text-muted">จำนวนเซิร์ฟเวอร์</span>
                                    </div>
                                </a>
                            </div>
                            <div class="col-sm-6 col-lg-3">
                                <a href="javascript:void(0)" class="widget">
                                    <div class="widget-content widget-content-mini text-right clearfix">
                                        <div class="widget-icon pull-left themed-background-danger">
                                            <i class="fa fa-users text-light-op"></i>
                                        </div>
                                        <h2 class="widget-heading h3 text-danger">
                                            <strong><span data-toggle="counter" data-to="<?php echo $stats -> totalUsers($odb); ?>"></span></strong>
                                        </h2>
                                        <span class="text-muted">ผู้ใช้ทั้งหมด</span>
                                    </div>
                                </a>
                            </div>
                            
                        </div>
            
                        <div class="row">
                          
						<div class="col-sm-6 col-lg-8">
 
							<div class="widget">
								<div class="widget-content widget-content-mini themed-background-dark text-light-op">
								<span class="pull-right text-muted"><?php echo htmlspecialchars($sitename); ?></span>
								<i class="fa fa-send"></i> <b>ข่าวสาร</b>
								</div>
								
								<div class="widget-content">
									<div style="position: relative; width: auto" class="slimScrollDiv">
										<div id="stats">
											<table class="table table-striped">
												<tbody>
													<tr>
														<th><center>หัวข้อ</center></th>
														<th><center>บทความ</center></th>
														<th><center>วันที่</center></th>
														<th><center>ผู้อัพโหลด</center></th>
													</tr>
													<tr>
													
													</tr>
													<?php
													$newssql = $odb -> query("SELECT * FROM `news` ORDER BY `date` DESC LIMIT 4");
													while($row = $newssql ->fetch())
													{
													$id = $row['ID'];
													$title = $row['title'];
													$content = $row['content'];
													$autor = $row['author'];
													echo '
													<tr>
															<td><center>'.htmlspecialchars($title).'</center></td>
															<td><center>'.htmlspecialchars($content).'</center></td>
															<td><center> '.date("d/m/y" ,$row['date']).'</center></td>
															<td><center><span class="label label-success">'.htmlspecialchars($autor).'</span></center></td>
														</div>
													</tr>';
													}
													?>			
												</tbody>
											</table>
										</div>
									</div>
								
								</div>
							</div>
						 
						</div>
						<div class="col-sm-6 col-lg-4">
							<div class="widget">
								<div class="widget-content widget-content-mini themed-background-dark text-light-op">
								<span class="pull-right text-muted"><?php echo htmlspecialchars($sitename); ?></span>
								<i class="fa fa-user"></i> <b>ข้อมูลสมาชิก</b>
								</div>
								
								<div class="widget-content">
									<table class="table table-striped table-vcenter">
									<?php
									$plansql = $odb -> prepare("SELECT `users`.`expire`, `plans`.`name`, `plans`.`concurrents`, `plans`.`mbt` FROM `users`, `plans` WHERE `plans`.`ID` = `users`.`membership` AND `users`.`ID` = :id");
									$plansql -> execute(array(":id" => $_SESSION['ID']));
									$rowxd = $plansql -> fetch(); 
									$date = date("d/m/Y, h:i a", $rowxd['expire']);
									if (!$user->hasMembership($odb))
									{
									$rowxd['mbt'] = 0;
									$rowxd['concurrents'] = 0;
									$rowxd['name'] = 'กรุณาเช่าแพลนก่อนคับ';
									$date = 'กรุณาเช่าแพลนก่อนคับ';
									}
									?>

									<tbody>
										<tr>
											<td class="text-right"><strong>ชื่อผู้ใช้งาน</strong></td>
											<td><?php echo $_SESSION['username']; ?></td>
										</tr>
										<tr>									
											<td class="text-right" style="width: 50%;"><strong>แพลน</strong></td>
											<td><?php echo htmlspecialchars($rowxd['name']); ?> <a data-original-title="Upgrade" href="purchase.php" data-toggle="tooltip" title=""><i class="fa fa-chevron-up"></i></a></td>
										</tr>
										<tr>
											<td class="text-right"><strong>วันหมดอายุ</strong></td>
											<td><?php echo $date; ?></td>
										</tr>
										<tr>
											<td class="text-right"><strong>เวลาสูงสุดในการยิง</strong></td>
											<?php
											if (!$user->hasMembership($odb))
											{
												echo '<td>กรุณาเช่าแพลนก่อนคับ</td>';
											} else {
											?>
											<td><?php echo $rowxd['mbt']; ?> วินาที</td>
											<?php } ?>
										</tr>
										<tr>	
											<td class="text-right"><strong>โจมตีไปแล้ว</strong></td>
											<?php
											if (!$user->hasMembership($odb))
											{
												echo '<td>กรุณาเช่าแพลนก่อนคับ</td>';
											} else {
											?>
											<td><?php echo $rowxd['concurrents']; ?> ครั้ง</td>
											<?php } ?>
										</tr>
									</tbody>
									</table>
								
								</div>
							</div>
						</div>
					</div>	
						
					 
				</div>
                     <? // NO BORRAR LOS TRES DIVS! ?>
               </div>
               </div>
             
          </div>

		<?php include("@/script.php"); ?>
    </body>
</html>