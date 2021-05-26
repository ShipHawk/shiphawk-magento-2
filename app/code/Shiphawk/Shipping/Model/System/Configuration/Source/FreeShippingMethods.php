<?php

namespace Shiphawk\Shipping\Model\System\Configuration\Source;

use Magento\Framework\Data\OptionSourceInterface;

class FreeShippingMethods implements OptionSourceInterface
{
  /**
   * Returns free methods list
   *
   * @return array
   */
  public function toOptionArray() {
    $services = [
      'FedEx 2 Day',                                                   
      'FedEx 2 Day Am',                                                
      'FedEx Express Saver',                                           
      'FedEx First Overnight',
      'FedEx First Overnight Saturday Delivery',                       
      'FedEx Ground',                                                  
      'FedEx Ground Home Delivery',                                    
      'FedEx International Economy',                                   
      'FedEx International First',                                     
      'FedEx International Ground',                                    
      'FedEx International Priority',                                  
      'FedEx Priority Overnight',                                      
      'FedEx Priority Overnight Saturday Delivery',                    
      'FedEx Standard Overnight',                                      
      'First Class Mail International',                                
      'First Class Package International Service',                     
      'First-Class Mail',                                              
      'Global Express Guaranteed',                                     
      'Library Mail',                                                  
      'Media Mail',                                                    
      'Parcel Select Ground',                                          
      'Priority Mail',                                                 
      'Priority Mail Express',                                         
      'Priority Mail Express Flat Rate Envelope',                      
      'Priority Mail Express Flat Rate Legal Envelope',                
      'Priority Mail Express International',                           
      'Priority Mail Express International Flat Rate Envelope',        
      'Priority Mail Express International Flat Rate Legal Envelope',  
      'Priority Mail Express International Flat Rate Padded Envelope', 
      'Priority Mail Flat Rate Envelope',                              
      'Priority Mail Flat Rate Legal Envelope',                        
      'Priority Mail International',                                   
      'Priority Mail International Flat Rate Envelope',                
      'Priority Mail International Flat Rate Legal Envelope',          
      'Priority Mail International Flat Rate Padded Envelope',         
      'Priority Mail International Large Flat Rate Box',               
      'Priority Mail International Medium Flat Rate Box',              
      'Priority Mail International Small Flat Rate Box',               
      'Priority Mail Large Flat Rate Box',                             
      'Priority Mail Medium Flat Rate Box',                            
      'Priority Mail Small Flat Rate Box',                             
      'UPS Ground',                                                    
      'UPS Next Day Air',                                              
      'UPS Next Day Air Early',                                        
      'UPS Next Day Air Saver',                                        
      'UPS Second Day Air',                                            
      'UPS Second Day Air A.M.',                                       
      'UPS Standard',                                                  
      'UPS SurePost',                                                  
      'UPS Three-Day Select',                                          
      'UPS Worldwide Expedited',                                       
      'UPS Worldwide Express',                                         
      'UPS Worldwide Express Freight',                                 
      'UPS Worldwide Express Plus',                                    
      'UPS Worldwide Saver',                                           
    ];

    $options = [];

    foreach($services as $service) {
      $options[] = ['value' => $service, 'label' => $service];
    }

    return $options;
  }
}
