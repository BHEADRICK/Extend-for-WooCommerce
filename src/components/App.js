import React, { useEffect} from "react";

import "./App.css";
// import 'bootstrap-grid-only-css/dist/css/bootstrap-grid.min.css';
import 'bootstrap/dist/css/bootstrap.css'
import ReactPaginate from 'react-paginate';

const root = window.extend_wc.root;
const nonce = window.extend_wc.nonce;
const path = window.extend_wc.versionString;

export default class App extends React.Component {
    state = {
        items: [],
        total: null,
        next: null,
        operation: null,
        DataisLoaded: false,
        offset: 0,
        perPage: 20,
        order_num : ''

    };




// handleClick = buttonName => {
    //     this.setState(calculate(this.state, buttonName));
    // };

    loadContractsFromServer=()=>{
        fetch(
            root + path + '?limit=' + this.state.perPage +  '&offset=' + this.state.offset + '&order_num='+ this.state.order_num + '&status=' + this.state.status, {
                headers: {'X-WP-Nonce': nonce}
            })
            .then((res) => res.json())
            .then((json) => {
                console.log(json)
                this.setState({
                    items: json.items,
                    DataisLoaded: true,
                    pageCount: Math.ceil(json.filtered_count /this.state.perPage),
                    totalRevenue: json.totals.revenue,
                    totalCount: json.totals.count,
                    monthRevenue: json.month.revenue,
                    monthCount: json.month.count,
                    products: json.products
                });
            })

}
handleOrderNumber =(e)=>{

        let val = e.target.value;
        console.log(val)
        if(val.length>2 || val.length === 0){
            this.setState({
                offset: 0,
                order_num:val
            }, ()=>{
                this.loadContractsFromServer();
            });

        }
}
handleStatus=(e)=>{
 this.setState({
     status: e.target.value
 }, ()=>{
     this.loadContractsFromServer();
 })
}

    componentDidMount(){

           this.loadContractsFromServer()

    }


    handlePageClick = (data) => {
        let selected = data.selected;
        let offset = Math.ceil(selected * this.state.perPage);

        this.setState({ offset: offset }, () => {
            this.loadContractsFromServer();
        });
    };
    render() {
        const { DataisLoaded, items, totalRevenue, totalCount, monthRevenue, monthCount, products} = this.state;

        const today = new Date();
        if (!DataisLoaded) return <div>
            <h1> Loading... </h1> </div> ;
        return (
            <div className="component-app bootstrap-wrapper">
                <div className="container">
                    <div className="col-md-4">
                        <h3>Total Warranties Sold</h3>
                        <div className="content">
                            <span>
                            {totalCount}
                            </span>
                        </div>
                    </div>
                    <div className="col-md-4">
                        <h3>Total Gross Warranty Revenue</h3>
                        <div className="content">
                            <span>
                                ${parseInt(totalRevenue).toLocaleString()}
                            </span>
                        </div>
                    </div>
                    <div className="col-md-4">
                        <h3>Warranties Sold (Current Month)</h3>
                        <div className="content">
                            <span>
                                {monthCount}
                            </span>
                        </div>
                    </div>
                    <div className="col-md-4">
                    <h2>placeholer</h2>
                        <div className="content">

                        </div>
                    </div>
                    <div className="col-md-4">
                        <h2>Popular Products</h2>
                        <div className="content">
                            <table>
                                <tbody>
                            {

                                Object.keys(products).map(key =>
                                    <tr key={key}>
                                        <td>{products[key].product_name}</td><td>{products[key].count}</td></tr>
                                )
                            }
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div className="col-md-4">
                        <h2>placeholder</h2>
                        <div className="content">

                        </div>
                    </div>
                </div>

                <table className="table" cellSpacing="0">
                    <thead>
                    <tr>
                        <th className="manage-column" scope="col">
                            <input type="text" className="form-control" onKeyUp={this.handleOrderNumber}
                                   aria-describedby="order Number" placeholder="Order#"/>
                        </th>
                        <th className="manage-column" scope="col">Date</th>
                        <th className="manage-column" scope="col">
                            <select name="status" onChange={this.handleStatus} >
                                <option value="">Status</option>
                                <option value="sent">Sent</option>
                                <option value="scheduled">Scheduled</option>
                                <option value="cancelled">Cancelled/Refunded</option>
                            </select>

                        </th>
                        <th className="manage-column" scope="col">Product</th>
                        <th className="manage-column" scope="col">Warranty Term</th>
                        <th className="manage-column" scope="col">Warranty Price</th>
                    </tr>
                    </thead>
                    <tbody>


                {
                    items.map((item) => (
                        <tr key = { item.id } >
                           <td>
                               <a target="_blank" href={"/wp-admin/post.php?post=" + item.order_id + "&action=edit"}>
                               {item.order_number}
                               </a>
                           </td>
                            <td>
                                {item.date_created}

                            </td>
                            <td>
                                {item.contract_number?(item.contract_number.length === 36?'sent':item.contract_number):'scheduled'}
                            </td>
                            <td>
                                <a target="_blank" href={"/wp-admin/post.php?post=" + item.product_id + "&action=edit"}>
                                {item.product_name}
                                </a>
                            </td>
                            <td>
                                {item.warranty_term} Months
                            </td>
                            <td>
                                {item.warranty_price}
                            </td>
                        </tr>
                    ))
                }
                    </tbody>
                </table>
                <ReactPaginate
                    previousLabel={'previous'}
                    nextLabel={'next'}
                    breakLabel={'...'}
                    breakClassName={'break-me'}
                    pageCount={this.state.pageCount}
                    marginPagesDisplayed={2}
                    pageRangeDisplayed={5}
                    onPageChange={this.handlePageClick}
                    containerClassName={'pagination'}
                    activeClassName={'active'}
                    pageClassName={'page-item'}
                    previousClassName={'page-item'}
                    nextClassName={'page-item'}
                    pageLinkClassName={'page-link'}
                    previousLinkClassName={'page-link'}
                    nextLinkClassName={'page-link'}
                />
            </div>
        );
    }
}