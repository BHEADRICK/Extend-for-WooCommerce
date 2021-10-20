import React from "react";

import "./App.css";
import 'bootstrap-grid-only-css/dist/css/bootstrap-grid.min.css';
let root = window.extend_wc.root;
let nonce = window.extend_wc.nonce;
let path = window.extend_wc.versionString;
export default class App extends React.Component {
    state = {
        items: [],
        total: null,
        next: null,
        operation: null,
        DataisLoaded: false
    };

    // handleClick = buttonName => {
    //     this.setState(calculate(this.state, buttonName));
    // };

    componentDidMount(){

            fetch(
                root + path, {
                    headers: {'X-WP-Nonce': nonce}
                })
                .then((res) => res.json())
                .then((json) => {
                    console.log(json)
                    this.setState({
                        items: json,
                        DataisLoaded: true
                    });
                })

    }



    render() {
        const { DataisLoaded, items } = this.state;
        const prods = items.reduce(function(acc,item){ if(!acc[item.product_name]){acc[item.product_name]=0}  acc[item.product_name]++; return acc},{});
        const today = new Date();
        if (!DataisLoaded) return <div>
            <h1> Loading... </h1> </div> ;
        return (
            <div className="component-app bootstrap-wrapper">
                <div className="container">
                    <div className="col-md-4">
                        <h2>Total Warranties Sold</h2>
                        <div className="content">
                            <span>
                            {items.length}
                            </span>
                        </div>
                    </div>
                    <div className="col-md-4">
                        <h2>Total Gross Warranty Revenue</h2>
                        <div className="content">
                            <span>
                            ${items.reduce(function(acc,item){
                                return parseFloat(acc) + parseFloat(item.warranty_price)
                            },0).toLocaleString()}
                            </span>
                        </div>
                    </div>
                    <div className="col-md-4">
                        <h2>Warranties Sold (Current Month)</h2>
                        <div className="content">
                            <span>
                            {
                                 items.filter(c=>{
                                    return new Date(c.date_created).getMonth() === today.getMonth();
                            }).length
                            }
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
                            {/*<table>*/}
                            {/*{*/}

                                {/*Object.keys(prods).map(key =>*/}
                                    {/*<tr>*/}
                                        {/*<td>{key}</td><td>{prods[key]}</td></tr>*/}
                                {/*)*/}
                            {/*}}*/}
                            {/*</table>*/}
                        </div>
                    </div>
                    <div className="col-md-4">
                        <h2>placeholder</h2>
                        <div className="content">

                        </div>
                    </div>
                </div>
                <table className="widefat fixed" cellspacing="0">
                    <thead>
                    <tr>
                        <th className="manage-column" scope="col">Order #</th>
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
            </div>
        );
    }
}