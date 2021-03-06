<?php if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class System_Setup extends MY_Controller
{


    public function __construct()
    {
        parent::__construct();
        $this->data['active'] = 'setup';
        $this->load->model('setup_model');
    }

    public function InvSnapShots(){
        $data=$this->data;
        $data['headline']='Inventory SnapShots';
        $data['subactive']='invsnapshots';
        $data['breadcrumbs']=array('System Setup'=>'#','Inventory Snapshots'=>'#');
        $this->load->view('setup/invsnaps',$data);
    }
    public function getInvSnapShots(){
        $this->load->library('datatables');
        $this->datatables->select('inventory_snaps_id,(SELECT name FROM outlets WHERE outlet_id=inventory_snaps.outlet_id) AS outlet,filename,creation_time');
        $this->datatables->from('inventory_snaps');
        $result=$this->datatables->generate();
        echo $result;
    }
    public function create_inv_sanp(){
        $outlets=$this->shared_model->getQuery("SELECT outlet_id,name FROM outlets");
        for($i=0;$i<count($outlets);$i++){
            $filename='ss-'.date('Y-m-d')."-".$outlets[$i]->name.".csv";
            $myfile = fopen('uploads/'.$filename, "w") or show_error("Unable to open file!");
            $header = "Barcode,Description,Stock No,Cost,QTY\n";
            fwrite($myfile,$header);
            $tdata=$this->shared_model->getQuery('SELECT barcode,description,stock_num,stock_outlets.cost_price,stock_outlets.qty as qty FROM stock
                join stock_outlets using(item_id) WHERE stock_outlets.outlet_id='.$outlets[$i]->outlet_id);
                for($j=0;$j<count($tdata);$j++){
                    $s=$tdata[$j]->barcode.',"'.$tdata[$j]->description.'",'.$tdata[$j]->stock_num.','.$tdata[$j]->cost_price.','.$tdata[$j]->qty."\n";
                    fwrite($myfile,$s);
                }
            fclose($myfile);
            $this->shared_model->insert('inventory_snaps',array(
                'outlet_id'=>$outlets[$i]->outlet_id,
                'filename'=>$filename,
                'creation_time'=>date('Y-m-d H:i:s')
                ));
        }
        $this->session->set_flashdata('success','SnapShots Created.');
        redirect('system_setup/InvSnapShots');
    }

    public function users()
    {
        $data = $this->data;
        $data['user_types'] = $this->shared_model->getRecords('user_types');
        $data['users'] = $this->setup_model->getUsers();
        $data['headline'] = 'User Setup';
        $data['subactive'] = 'users';
        $data['breadcrumbs'] = array('System Setup' => '#', 'Users' => '#');
        $this->load->view('setup/users', $data);
    }

    public function add_user($id = false)
    {
        $data = $this->data;
        $data['headline'] = 'User Setup';
        $data['subactive'] = 'users';
        $data['breadcrumbs'] = array('System Setup' => '#', 'Add User' => '#');
        $data['user_types'] = $this->shared_model->getRecords('user_types');
		$data['outlets']=$this->shared_model->getRecords('outlets');
        if ($id) {
            $user = $this->shared_model->getRecords('users', '', '', array('user_id' => $id));
            $data['ip_addresses'] = $this->shared_model->getRecords('allowed_ips', '', '', array('user_id' => $id));
            $data['user'] = $user[0];
        }
        $this->load->view('setup/add_user', $data);
    }

    public function check_username($id = false)
    {
        $username = $this->input->post('username');
        if ($username) {
            if ($id) {
                $userdata = $this->user_model->getUser($id);
                $check = $username == $userdata->username;
            } else {
                $check = false;
            }
            if ($this->user_model->username_taken($username) && !$check) {
                echo 'The username is already taken.';
            }
        }
    }

    public function save_user($id = false, $profile = false)
    {
        $user = $this->shared_model->getPost('users');
        if (isset($user['password'])) {
            $user['password'] = md5($user['password']);
        }
        if (!$id) {
            //$user['outlet_id'] = $this->session->userdata('outlet_id');
            $success = $this->shared_model->insert('users', $user);
            log_db_query($this->db->last_query());  // Log DB Query
            $id = $this->db->insert_id();
        } else {
            $success = $this->shared_model->update('users', 'user_id', $id, $user);
            log_db_query($this->db->last_query());  // Log DB Query
        }
        $this->db->trans_start();
        $ip_addresses = $this->input->post('ip_addresses');
        $num = $ip_addresses ? count($ip_addresses) : 0;
        $this->shared_model->delete('allowed_ips', 'user_id', $id);
        for ($i = 0; $i < $num; $i++) {
            if (trim($ip_addresses[$i]) != '') {
                $ip_address = array('user_id' => $id, 'ip_address' => $ip_addresses[$i]);
                $this->shared_model->insert('allowed_ips', $ip_address);
            }
        }
        $success = 1;
        $this->db->trans_complete();
        if ($success) {
            $this->session->set_flashdata('success', 'Entry is sucessfully saved.');
            if ($profile) {
                redirect();
            } else {
                redirect('system_setup/users');
            }
        } else {
            $this->session->set_flashdata('error', 'An error occurred. Please try again.');
            if ($profile) {
                redirect('system_setup/profile');
            } else {
                redirect('system_setup/add_user/' . $id);
            }

        }
    }

    public function delete_user($id = false)
    {
        if (!$id) {
            redirect('system_setup/users');
        }
        $success = $this->shared_model->update('users', 'user_id', $id, array('active' => 'N'));
        if ($success) {
            log_db_query($this->db->last_query());  // Log DB Query
            $this->session->set_flashdata('success', 'Entry is successfully deleted.');
        } else {
            $this->session->set_flashdata('error', 'An error occurred. Please try again.');
        }
        redirect('system_setup/users');
    }

    public function customers()
    {
        $data = $this->data;
        $data['headline'] = 'Customer Setup';
        $data['subactive'] = 'customers';
        $data['date_filter'] = date('Y-m-d');
        if (!defined('CAL_GREGORIAN'))
            define('CAL_GREGORIAN', 1);

        if ($this->input->post('date_from')) {
            $days = cal_days_in_month(CAL_GREGORIAN, date('m', strtotime($this->input->post('date_from'))), date('Y', strtotime($this->input->post('date_from'))));
            $data['date_filter'] = date('Y-m-d', strtotime($days . '-' . $this->input->post('date_from')));
        }
        $data['breadcrumbs'] = array('System Setup' => '#', 'Customers' => '#');
        $this->load->view('setup/customers', $data);
    }

    public function getCustomers()
    {

        $customers = $this->setup_model->getCustomers();
        echo $customers;
    }

    public function customer_payments($customer_id)
    {
        $data = $this->data;
        $data['headline'] = 'Customer Payments';
        $data['subactive'] = 'customers';
        $data['breadcrumbs'] = array('System Setup' => '#', 'Customers' => 'system_setup/customers', 'Customer Payments' => '#');
        $this->load->view('setup/customer_payments', $data);
    }

    public function getCustomerPayments($customer_id)
    {
        $payments = $this->setup_model->getCustomerPayments($customer_id);
        $payments = json_decode($payments, true);

        foreach ($payments['aaData'] as $key => $res) {
            $payments['aaData'][$key]['0'] = format_price($res['0']);
            $payments['aaData'][$key]['1'] = $res['1'];
            $payments['aaData'][$key]['2'] = format_date($res['2']);
        }

        $payments = json_encode($payments);
        echo $payments;
    }


    public function make_payment($customer_id)
    {

        $amount = $this->input->post('amount');
        $description = $this->input->post('description');
        $year = $this->input->post('dp-year');
        $month = $this->input->post('dp-month');
        $customer_id = $this->uri->segment(3);

        $date = new DateTime($year . '-' . $month);
        $date->modify('last day of this month');
        $date->setTime(23, 59, 59);
        $lastDayOfMonth = $date->format('Y-m-d H:i:s');
        $d=$this->input->post('pmt_date');


        $payment_data = array(
            'customer_id' => $customer_id,
            'outlet_id' => $this->session->userdata('outlet_id'),
            'amount' => $amount,
            'description' => $description,
            'date' => $d//$lastDayOfMonth
        );

        $this->db->trans_start();
        $this->db->insert('customer_payment_history', $payment_data);
        log_db_query($this->db->last_query());  // Log DB Query
        $this->db->trans_complete();
        redirect('system_setup/customer_payments/' . $customer_id . '/' . $this->input->post('dp-year') . '-' . $this->input->post("dp-month"));

    }

    public function add_customer($id = false)
    {
        $data = $this->data;
        $data['headline'] = 'Customer Setup';
        $data['subactive'] = 'customers';
        $data['breadcrumbs'] = array('System Setup' => '#', 'Add Customer' => '#');
        if ($id) {
            $customer = $this->shared_model->getRecords('customers', '', '', array('customer_id' => $id));
            $data['customer'] = $customer[0];
        }
        $this->load->view('setup/add_customer', $data);
    }

    public function delete_customer($id)
    {
        if (!$id) {
            redirect('system_setup/customers');
        }
        $success = $this->shared_model->update('customers', 'customer_id', $id, array('active' => 'N'));
        if ($success) {
            log_db_query($this->db->last_query());  // Log DB Query
            $this->session->set_flashdata('success', 'Entry is successfully deleted.');
        } else {
            $this->session->set_flashdata('error', 'An error occurred. Please try again.');
        }
        redirect('system_setup/customers');
    }

    public function save_customer($id = false)
    {
        $customer = $this->shared_model->getPost('customers');
        if (!$id) {
            $success = $this->shared_model->insert('customers', $customer);
        } else {
            $success = $this->shared_model->update('customers', 'customer_id', $id, $customer);
        }
        if ($success) {
            log_db_query($this->db->last_query());  // Log DB Query
            $this->session->set_flashdata('success', 'Entry is sucessfully saved.');
            redirect('system_setup/customers');
        } else {
            $this->session->set_flashdata('error', 'An error occurred. Please try again.');
            redirect('system_setup/add_customer/' . $id);
        }
        /* $this->session->set_flashdata('success', $customer);
         redirect('system_setup/customers');*/

    }

    public function saveCustomerAjax()
    {
        $customer = $this->shared_model->getPost('customers');
        $this->shared_model->insert('customers', $customer);
        log_db_query($this->db->last_query());  // Log DB Query
        $id = mysql_insert_id();
        echo $id;
    }

    public function suppliers()
    {
        $data = $this->data;
        $data['tablesort'] = true;
        $data['headline'] = 'Supplier Setup';
        $data['subactive'] = 'suppliers';
        $data['breadcrumbs'] = array('System Setup' => '#', 'Suppliers' => '#');
        $data['suppliers'] = $this->setup_model->getSuppliers();
        $this->load->view('setup/suppliers', $data);
    }

    public function add_supplier($id = false)
    {
        $data = $this->data;
        $data['headline'] = 'Supplier Setup';
        $data['subactive'] = 'suppliers';
        $data['breadcrumbs'] = array('System Setup' => '#', 'Add Supplier' => '#');
        if ($id) {
            $supplier = $this->shared_model->getRecords('suppliers', '', '', array('supplier_id' => $id));
            $data['supplier'] = $supplier[0];
        }
        $this->load->view('setup/add_supplier', $data);
    }

    public function delete_supplier($id = false)
    {
        if (!$id) {
            redirect('system_setup/suppliers');
        }
        $success = $this->shared_model->update('suppliers', 'supplier_id', $id, array('active' => 'N'));
        if ($success) {
            log_db_query($this->db->last_query());  // Log DB Query
            $this->session->set_flashdata('success', 'Entry is successfully deleted.');
        } else {
            $this->session->set_flashdata('error', 'An error occurred. Please try again.');
        }
        redirect('system_setup/suppliers');
    }


    public function save_supplier($id = false)
    {
        $supplier = $this->shared_model->getPost('suppliers');
        if (!$id) {
            $success = $this->shared_model->insert('suppliers', $supplier);
        } else {
            $success = $this->shared_model->update('suppliers', 'supplier_id', $id, $supplier);
        }
        if ($success) {
            log_db_query($this->db->last_query());  // Log DB Query
            $this->session->set_flashdata('success', 'Entry is sucessfully saved.');
            redirect('system_setup/suppliers');
        } else {
            $this->session->set_flashdata('error', 'An error occurred. Please try again.');
            redirect('system_setup/add_supplier/' . $id);
        }
    }

    public function getSuppliersDetails($id)
    {
        $data['suppliers'] = $this->setup_model->supplierDetails($id);
        $json = $this->shared_model->JEncode($data['suppliers']);
        echo $json;
    }

    public function getCustomersDetails($id)
    {
        $data['customers'] = $this->setup_model->customerDetails($id);
        $json = $this->shared_model->JEncode($data['customers']);
        echo $json;
    }

    public function outlets()
    {
        $data = $this->data;
        $data['tablesort'] = true;
        $data['headline'] = 'Outlet Setup';
        $data['subactive'] = 'outlets';
        $data['breadcrumbs'] = array('System Setup' => '#', 'Outlets' => '#');
        $data['outlets'] = $this->setup_model->getOutlets(0, 100);
        $this->load->view('setup/outlets', $data);
    }

    public function add_outlet($id = false)
    {
        $data = $this->data;
        $data['headline'] = 'Outlet Setup';
        $data['subactive'] = 'outlets';
        $data['breadcrumbs'] = array('System Setup' => '#', 'Add Outlet' => '#');
        if ($id) {
            $outlet = $this->shared_model->getRecords('outlets', '', '', array('outlet_id' => $id));
            $data['outlet'] = $outlet[0];
        }
        $this->load->view('setup/add_outlet', $data);
    }

    public function save_outlet($id = false)
    {
        $outlet = $this->shared_model->getPost('outlets', false, array('address2','fax'));
        if (!$id) {
            $success = $this->shared_model->insert('outlets', $outlet);
        } else {
            $success = $this->shared_model->update('outlets', 'outlet_id', $id, $outlet);
        }
        if ($success) {
            log_db_query($this->db->last_query());  // Log DB Query
            $this->session->set_flashdata('success', 'Entry is sucessfully saved.');
            redirect('system_setup/outlets');
        } else {
            $this->session->set_flashdata('error', 'An error occurred. Please try again.');
            redirect('system_setup/add_outlet/' . $id);
        }
    }

    public function categories()
    {
        $data = $this->data;
        $data['tablesort'] = true;
        $data['headline'] = 'Categories Setup';
        $data['subactive'] = 'categories';
        $data['breadcrumbs'] = array('System Setup' => '#', 'Categories' => '#');
        $this->load->view('setup/categories', $data);
    }

    public function getCategories()
    {
        $categories = $this->setup_model->getCategories();
        echo $categories;
    }

    public function delete_category($id)
    {
        $category = $this->shared_model->Lookup('categories', 'category', array('category_id' => $id));
        $exists = $this->shared_model->Lookup('stock', 'item_id', array('category' => $category));
        if (!$exists) {
            $success = $this->shared_model->delete('categories', 'category_id', $id);
            if ($success) {
                log_db_query($this->db->last_query());  // Log DB Query
                $this->session->set_flashdata('success', 'Entry is successfully deleted.');
            } else {
                $this->session->set_flashdata('error', 'An error ocurred. Please try again.');
            }
        } else {
            $this->session->set_flashdata('error', 'You can\'t delete this category. Stock Items with the selected category exist.');
        }
        redirect('system_setup/categories');
    }

    public function add_category($id = false)
    {
        $data = $this->data;
        $data['headline'] = 'Category Setup';
        $data['subactive'] = 'categories';
        $data['breadcrumbs'] = array('System Setup' => '#', 'Add Category' => '#');
        if ($id) {
            $category = $this->shared_model->getRecords('categories', '', '', array('category_id' => $id));
            $data['category'] = $category[0];
        }
        $this->load->view('setup/add_category', $data);
    }

    public function save_category($id = false)
    {
        $category = $this->shared_model->getPost('categories');
        if ($id) {
            $category_code = $this->shared_model->Lookup('categories', 'category', array('category_id' => $id));
            if (!isset($category['category'])) {
                $category['category'] = '';
            }
            $this->db->trans_start();
            $this->shared_model->update('stock', 'category', $category_code, array('category' => $category['category']));
            log_db_query($this->db->last_query());  // Log DB Query
            $this->shared_model->update('categories', 'category_id', $id, $category);
            log_db_query($this->db->last_query());  // Log DB Query
            $success = true;
            $this->db->trans_complete();
        } else {
            $category_name = $this->shared_model->Lookup('categories', 'category', array('category' => $category['category']));
            if( $category_name ){
                $this->session->set_flashdata('error', 'Category "'.$category['category'].'" already exist.');
                redirect('system_setup/categories');
            }else{
                $success = $this->shared_model->insert('categories', $category);
            }
        }
        if ($success) {
            log_db_query($this->db->last_query());  // Log DB Query
            $this->session->set_flashdata('success', 'Entry is successfully saved.');
            redirect('system_setup/categories');
        } else {
            $this->session->set_flashdata('error', 'An error occurred or no changes were made!');
            redirect('system_setup/category/' . $id);
        }
    }

    public function delete_outlet($id = false)
    {
        if (!$id) {
            redirect('system_setup/outlets');
        }
        $success = $this->shared_model->update('outlets', 'outlet_id', $id, array('active' => 'N'));
        if ($success) {
            log_db_query($this->db->last_query());  // Log DB Query
            $this->session->set_flashdata('success', 'Entry is successfully deleted.');
        } else {
            $this->session->set_flashdata('error', 'An error occurred. Please try again.');
        }
        redirect('system_setup/outlets');
    }

    public function user_permissions()
    {
        $data = $this->data;
        $data['headline'] = 'User Permissions';
        $data['subactive'] = 'user_permissions';
        $data['breadcrumbs'] = array('User Permissions' => '#');
        $this->load->helper('inflector');
        $types = $this->shared_model->LookupArray('user_types', 'type_id', 'type');
        $data['tabs'] = array();
        $data['permissions'] = array();
        $data['user_types'] = array();
        foreach ($types as $key => $value) {
            $data['tabs'][$key] = array('tab_name' => underscore($value), 'type_name' => $value);
            $data['user_types'][$key] = $this->shared_model->getRow('select * from user_types where type_id="' . $key . '"');
        }
        $permissions = $this->shared_model->getQuery('DESCRIBE user_types', true);
        foreach ($permissions as $permission) {
            if ($permission['Field'] != 'type' && $permission['Field'] != 'type_id') {
                $field = explode('_', $permission['Field'], 2);
                if (!isset($data['permissions'][$field[0]])) {
                    $data['permissions'][$field[0]] = array();
                }
                array_push($data['permissions'][$field[0]], $permission['Field']);
            }
        }
        $this->load->view('setup/user_permissions', $data);
    }

    public function update_permissions()
    {
        $types = $this->input->post();
        $type_ids = $this->shared_model->LookupArray('user_types', 'type_id', 'type_id');
        $fields_temp = $this->shared_model->getQuery('DESCRIBE user_types', true);
        $fields = array();
        foreach ($fields_temp as $field) {
            if ($field['Field'] != 'type' && $field['Field'] != 'type_id') {
                array_push($fields, $field['Field']);
            }
        }
        foreach ($type_ids as $type_id) {
            $permissions = array();
            foreach ($fields as $field) {
                $permissions[$field] = isset($types[$field . '_' . $type_id]) ? $types[$field . '_' . $type_id] : 'N';
            }
            $this->shared_model->update('user_types', 'type_id', $type_id, $permissions);
            log_db_query($this->db->last_query());  // Log DB Query
        }

        $this->session->set_flashdata('success', 'User Permissions are successfully updated.');
        redirect('system_setup/user_permissions');
    }

    public function profile()
    {
        $data = $this->data;
        $data['headline'] = 'Profile';
        $data['active'] = '';
        $data['breadcrumbs'] = array('Profile' => '#');
        $id = $this->session->userdata('user_id');
        $user = $this->shared_model->getRecords('users', '', '', array('user_id' => $id));
        $data['user'] = $user[0];
        $data['profile'] = 1;
        $this->load->view('setup/add_user', $data);
    }

}

/* End of file welcome.php */
/* Location: ./application/controllers/welcome.php */